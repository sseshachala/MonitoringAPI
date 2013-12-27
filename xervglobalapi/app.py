import os
import datetime
import json
from functools import wraps
import shlex
import subprocess

from flask import Flask
from flask.ext.mongoengine import MongoEngine

from flask import jsonify, request

from celery import Celery

from werkzeug.exceptions import default_exceptions
from werkzeug.exceptions import HTTPException, BadRequest

__all__ = ['make_json_app']


def make_celery(app):
    celery = Celery(app.import_name, broker=app.config['CELERY_BROKER_URL'])
    celery.conf.update(app.config)
    TaskBase = celery.Task
    class ContextTask(TaskBase):
        abstract = True
        def __call__(self, *args, **kwargs):
            with app.app_context():
                return TaskBase.__call__(self, *args, **kwargs)
    celery.Task = ContextTask
    return celery

def make_json_app(import_name, **kwargs):
    """
    Creates a JSON-oriented Flask app.

    All error responses that you don't specifically
    manage yourself will have application/json content
    type, and will contain JSON like this (just an example):

    { "message": "405: Method Not Allowed" }
    """
    def make_json_error(ex):
        response = jsonify(message=str(ex))
        response.status_code = (ex.code
                                if isinstance(ex, HTTPException)
                                else 500)
        return response

    app = Flask(import_name, **kwargs)
    for code in default_exceptions.iterkeys():
        app.error_handler_spec[None][code] = make_json_error

    return app

app = make_json_app(__name__)

#### CONFIG PART ####

PROJECT_PATH = os.path.dirname(os.path.abspath(__file__))
DEBUG = True
TEST_JSON = os.path.join(PROJECT_PATH, 'test.json')

app.config.from_object(__name__)
app.config.from_pyfile(os.path.join(PROJECT_PATH, 'settings.py'))

CONFIG_PATH = os.path.expanduser('~/etc/xervpyapi/xervpyapi.conf')

if os.path.exists(CONFIG_PATH):
    app.config.from_pyfile(CONFIG_PATH)

#### END CONFIG PART ####

celery = make_celery(app)

db = MongoEngine(app)

#### MODELS ####


class Plan(db.Document):
    """Model for general plan
    """
    name = db.StringField(max_length=120, required=True, unique=True)
    checks = db.ListField(db.StringField(), default=list, required=True)
    exclude_checks = db.ListField(db.StringField(), default=list)
    include_plans = db.ListField(db.ReferenceField('self',
        reverse_delete_rule=db.PULL), default=list)
    active = db.BooleanField(default=True)
    added = db.DateTimeField(default=datetime.datetime.now)
    edited = db.DateTimeField(default=datetime.datetime.now)

    @db.queryset_manager
    def active_plans(doc_cls, queryset):
        return queryset.filter(active=True)

    def __unicode__(self):
        return '%s' % self.name

    def plan_checks(self):
        checks = set(self.checks)
        for plan in self.include_plans:
            checks = checks.union(plan.plan_checks())
        if self.exclude_checks:
            checks = checks - set(self.exclude_checks)
        return list(checks)

#### END MODELS ####


def ensure_json(json_params):
    """Decorator for flask method to ensure parameters in json request

    """
    def decor(f):
        @wraps(f)
        def wrapper(*args, **kwargs):
            try:
                json_data = request.json
            except BadRequest:
                return failed_response("No json data")
            if not json_data:
                return failed_response("No json data")
            missed_params = []
            for param in json_params:
                if param not in json_data:
                    missed_params.append(param)

            if missed_params:
                return failed_response("Missing json params: (%s) in request data" %
                                            ','.join(missed_params))
            return f(*args, **kwargs)
        return wrapper
    return decor


def get_interface_ip(ifname='eth0'):
    """Resolve interface of current machine

    :param str ifname: Interface to resolve ip
    """
    import socket
    import fcntl
    import struct
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        return socket.inet_ntoa(fcntl.ioctl(s.fileno(), 0x8915,
                                struct.pack('256s', ifname[:15]))[20:24])
    except Exception, e:
        return socket.gethostbyname(socket.gethostname())

SERVER_IP = get_interface_ip()

#### AUTH BLOCK ####


def check_auth(username, password):
    """Check auth method

    """
    return username == 'xervmon' and password == 'xervmon_pass'


def authenticate():
    """Authenticate function
    """
    message = {'message': "Authenticate."}
    resp = jsonify(message)

    resp.status_code = 401
    resp.headers['WWW-Authenticate'] = 'Basic realm="Restricted"'

    return resp


def requires_auth(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        auth = request.authorization
        if not auth:
            return authenticate()

        elif not check_auth(auth.username, auth.password):
            return authenticate()
        return f(*args, **kwargs)

    return decorated


@app.route('/authenticate')
def dummy_auth():
    return response_data(broker_ip=SERVER_IP)


#### END AUTH BLOCK ####

#### Plan methods ####


def copy_plans():
    with open(TEST_JSON) as fp:
        data = json.load(fp)
    return data['plans']


def load_plans():
    """Load plans from db

    and extend any include plans
    """
    plans = {}
    for plan in Plan.active_plans():
        plans[plan.name] = plan.plan_checks()
    return plans


def update_plans(plan_data, not_create=False, only_create=False, replace=False):
    #TODO Refactor this function because of complexity
    name = plan_data['name']
    plan = Plan.objects(name__exact=name).first()
    if only_create and plan:
        return False, "Plan '%s' already exists" % name
    if (not_create or replace) and not plan:
        return False, "Plan '%s' does not exist" % name
    if not plan:
        plan = Plan(name=name)
    include_plans = plan_data.get('include_plans', [])
    if replace and include_plans:
        plan.include_plans = []
    for incl_plan in include_plans:
        add_plan = Plan.objects(name__exact=incl_plan).first()
        if not add_plan:
            return False, "Plan '%s' does not exist from 'include_plans'" % (
                                                                incl_plan,)
        plan.include_plans.append(add_plan)

    for var in ('checks', 'exclude_checks'):
        data_val = plan_data.get(var)
        if replace:
            setattr(plan, var, data_val if data_val else [])
            continue
        if not data_val:
            continue
        new_val = set(getattr(plan, var)).union(set(data_val))
        setattr(plan, var, list(new_val))
    plan_active = plan_data.get('active')
    if plan_active is not None:
        plan.active = plan_active
    plan.save()

    return True, None


#### END PLAN METHODS ####


def response_data(**kwargs):
    items = {}
    data = kwargs.get('data', {})
    for key, value in kwargs.items():
        if isinstance(value, set):
            items[key] = list(value)
        else:
            items[key] = value
    data.update(items)
    data['status'] = 'OK'
    print data
    resp = jsonify(data)
    return resp


def failed_response(msg):
    data = {}
    data['message'] = msg
    data['status'] = 'error'
    return jsonify(data)


@app.after_request
def after_request(response):
    return response


@app.errorhandler(404)
def not_found(error=None):
    """Error handler

    """
    message = {
        'status': 'error',
        'message': 'Not Found: ' + request.url,
    }
    resp = jsonify(message)
    resp.status_code = 404
    return resp


@app.route('/get_plans')
def get_plans():
    """Get all active plans

    Return all plans checks

    **Example request**:

    .. sourcecode:: http

        GET /get_plans
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type:    application/json

        {
          "status": "OK",
          "checks":{
          "test": [
            "bla_plan"
          ],
          "professional": [
            "uname",
            "netstat"
          ],
          "basic": [
            "cpu"
          ]
          }
        }
    """
    plans = load_plans()
    return response_data(checks=plans)


@app.route('/get_plan/<name>')
def get_plan(name):
    """Get plan info

    :param string name: plan name

    **Example request**:

    .. sourcecode:: http

        GET /get_plan/professional
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json

        {
          "status": "OK",
          "checks": {
          "professional": [
            "uname",
            "netstat"
          ]
          }
        }

    """
    plans = load_plans()
    plan = plans.get(name)
    if plan is None:
        return failed_response("No such plan %s" % name)
    return response_data(checks={name: plan})


@app.route('/delete_plan/<name>', methods=["GET"])
def delete_plan(name):
    """Delete plan

    :param string name: plan name

    **Example request**:

    .. sourcecode:: http

        GET /delete_plan/professional
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json

        {
          "status": "OK",
          "name": "professional"
        }

    """
    plan = Plan.objects(name__exact=name)
    if not plan:
        return failed_response("No plan '%s'" % name)
    plan.delete()
    return response_data(name=name)


@app.route('/add_plan', methods=["POST"])
@ensure_json(['name', 'checks'])
def add_plan():
    """Add new plan with checks

    If plan already exists returns error

    :jsonparam string name: plan to be changed
    :jsonparam list checks: checks in the plan
    :jsonparam bool active: if plan is active
    :jsonparam list include_plans:
        other existing plans.
        Checks from this plans will be included to this plan


    **Post data**:

    .. code-block:: json

        {
                "name": "Test",
                "include_plans": [
                        "Basic"
                        ],
                "checks":["bla_plan"],

                "active": true
        }


    **Example request**:

    .. sourcecode:: http

        POST /add_plan
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json
        {
            "status": "OK",
            "name": "Test"
        }

    """
    plan = request.json
    upd, msg = update_plans(plan, only_create=True)
    if upd:
        return response_data(name=plan['name'])
    else:
        return failed_response("Could not add plan. %s" % msg if msg is not
                            None else '')


@app.route('/update_plan', methods=["POST"])
@ensure_json(['name', 'checks'])
def update_plan():
    """Update plan

    Delete plan and create new with given params

    :jsonparam string name: plan to be changed
    :jsonparam list checks: checks in the plan
    :jsonparam bool active: if plan is active
    :jsonparam list include_plans:
        other existing plans.
        Checks from this plans will be included to this plan


    **Post data**:

    .. code-block:: json

        {
                "name": "Test",
                "include_plans": [
                        "Basic"
                        ],
                "checks":["bla_plan"],

                "active": true
        }


    **Example request**:

    .. sourcecode:: http

        POST /update_plan
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json
        {
            "status": "OK",
            "name": "Test"
        }

    """
    plan = request.json
    upd, msg = update_plans(plan, replace=True)
    if upd:
        return response_data(name=plan['name'])
    else:
        return failed_response("Could not update plan. %s" % msg if msg is not
                            None else '')

@app.route("/add_check/<plan>/<check>")
def add_check(plan, check):
    """Add check to given plan

    :param string plan: plan to be changed
    :param string check: check to be added to the plan

    **Example request**:

    .. sourcecode:: http

        GET /add_check/professional/new
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json
        {
          "status": "OK",
          "check": "new",
          "plan": "professional"
        }

    """
    upd, msg = update_plans({'name': plan, 'checks': [check]}, not_create=True)
    if upd:
        return response_data(check=check, plan=plan)
    else:
        return failed_response(msg)


@app.route("/delete_check/<plan>/<check>")
def delete_check(plan, check):
    """Deletes check from given plan

    Add check to exclude_checks list

    :param string plan: plan to be changed
    :param string check: check to be removed from the plan

    **Example request**:

    .. sourcecode:: http

        GET /delete_check/professional/new
        Accept: application/json, text/javascript
        Content-Type:    application/json

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json
        {
          "status": "OK",
          "check": "new",
          "plan": "professional"
        }

    """
    upd, msg = update_plans({'name': plan, 'exclude_checks': [check]},
            not_create=True)
    if upd:
        return response_data(check=check, plan=plan)
    else:
        return failed_response(msg)


@app.route("/create_activate", methods=["POST"])
@ensure_json(["site", "plan"])
def create_activate():
    """Create site and activate given plan

    :jsonparam string site: site name to create
    :jsonparam string plan: plan to activate
    """
    data = request.json
    plan_name = data['plan']
    plan = Plan.objects(name__exact=plan_name).first()
    if not plan:
        return failed_response("Plan '%s' does not exist" % plan_name)
    checks = plan.plan_checks()

    result = activate_site.delay(data['site'], checks)
    return response_data(task_id=result.task_id)


@app.route("/creation_task_state/<task_id>")
def check_creation_task(task_id):
    result = activate_site.AsyncResult(task_id)
    return response_data(state=result.state)


@celery.task(name="create_site_task")
def create_site(name):
    run('ls')
    # run('omd create %s' % name)


@celery.task(name="activate_plan_task")
def activate_plan(sitename, checks):
    import requests
    base_url = make_site_apiurl(sitename)
    methods = {
            'enable_checks': 'enable_checks'
            }



def make_site_apiurl(sitename):
    return app.config['SITE_API_URL'].format(sitename=sitename)


def run(command):
    p = subprocess.Popen(shlex.split(command))
    return


if __name__ == '__main__':
    app.run(host=app.config['APP_HOST'], port=app.config['APP_PORT'])
