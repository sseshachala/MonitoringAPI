import os
import re
import json
from functools import wraps
import shlex
import subprocess

from flask import Flask

from flask import jsonify, request
from werkzeug.exceptions import default_exceptions
from werkzeug.exceptions import HTTPException, BadRequest

__all__ = ['make_json_app']

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

PROJECT_PATH = os.path.dirname(os.path.abspath(__file__))
DEBUG = True
TEST_JSON = os.path.join(PROJECT_PATH, 'test.json')

app.config.from_object(__name__)
app.config.from_pyfile(os.path.join(PROJECT_PATH, 'settings.py'))

CONFIG_PATH = os.path.expanduser('~/etc/xervpyapi/xervpyapi.conf')
if os.path.exists(CONFIG_PATH):
    app.config.from_pyfile(CONFIG_PATH)


def ensure_json(json_params):
    def decor(f):
        @wraps(f)
        def wrapper(*args, **kwargs):
            try:
                json_data = request.json
            except BadRequest:
                return failed_response("No json data")
            for param in json_params:
                if param not in json_data:
                    return failed_response("No json param %s in request data" %
                            param)
            return f(*args, **kwargs)
        return wrapper
    return decor


def get_interface_ip(ifname='eth0'):
    import socket
    # import fcntl
    # import struct
    # s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    # return socket.inet_ntoa(fcntl.ioctl(s.fileno(), 0x8915, struct.pack('256s',
    #                         ifname[:15]))[20:24])
    return socket.gethostbyname(socket.gethostname())

SERVER_IP = get_interface_ip()

#### AUTH BLOCK ####

def check_auth(username, password):
    return username == 'xervmon' and password == 'xervmon_pass'

def authenticate():
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
    with open(TEST_JSON) as fp:
        data = json.load(fp)
    plans = {}
    for plan in data['plans']:
        if not plan['active']:
            continue
        checks = set(plan['checks'])
        name = plan['name'].lower()
        plans[name] = checks
    for plan in data['plans']:
        if not plan['active']:
            continue
        try:
            include_plans = plan['include_plans']
        except KeyError:
            continue
        for include in include_plans:
            try:
                incl_checks = plans[include]
            except KeyError:
                continue
            name = plan['name'].lower()
            plans[name] = plans[name].union(incl_checks)
        exclude_checks = plan.get('exclude_checks', [])
        for ex_check in exclude_checks:
            try:
                plans[name].remove(ex_check)
            except KeyError:
                pass
    return plans


def update_plans(plan):
    for key in ('checks', 'name', 'active'):
        if key not in plan:
            return False, "No %s in plan" % key
    with open(TEST_JSON) as fp:
        data = json.load(fp)
    for exist_plan in data['plans']:
        if plan['name'] == exist_plan['name']:
            data['plans'].remove(exist_plan)

    data['plans'].append(plan)
    with open(TEST_JSON, 'w') as fp:
        json.dump(data, fp)
        return True, None

    return False, None


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


@app.route('/get_templates')
def get_templates():
    """Get all active templates

    This method will be moved to global API

    Return all plans info

    **Example request**:

    .. sourcecode:: http

        GET /get_templates
        Accept: application/json, text/javascript

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json

        {
          "test": [
            "bla_plan"
          ],
          "professional": [
            "uname",
            "netstat"
          ],
          "status": "OK",
          "basic": [
            "cpu"
          ]
        }
    """
    plans = load_plans()
    return response_data(**plans)


@app.route('/get_template/<template>')
def get_template(template):
    """Get template info

    This method will be moved to global API

    :param string template: plan name

    **Example request**:

    .. sourcecode:: http

        GET /get_template/professional
        Accept: application/json, text/javascript

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json

        {
          "status": "OK",
          "plan": [
            "uname",
            "netstat"
          ],
          "name": "professional"
        }

    """
    plans = load_plans()
    plan = plans.get(template)
    if plan is None:
        return failed_response("No such plan %s" % template)
    return response_data(plan=plan, name=template)


@app.route('/add_template', methods=["POST"])
def add_template():
    """Add new plan with checks

    This method will be moved to global API

    The plan stores in json file for now. It will move to db
    If plan name already exists it will overwrite checks and update the file

    :jsonparam string name: plan to be changed
    :jsonparam list checks: checks in the plan
    :jsonparam bool active: if plan is active
    :jsonparam list include_plans: other existing plans. Checks from this plans will be included to this plan


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

        POST /add_template
        Accept: application/json, text/javascript

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
    if not plan:
        return failed_response("Wrong data in request")
    upd, msg= update_plans(plan)
    if upd:
        return response_data(name=plan['name'])
    else:
        return failed_response("Could not add plan. %s" % msg if msg is not
                None else '')


@app.route("/add_check/<plan>/<check>")
def add_check(plan, check):
    """Add check to given plan

    This method will be moved to global API

    :param string plan: plan to be changed
    :param string check: check to be added to the plan

    **Example request**:

    .. sourcecode:: http

        GET /add_check/professional/new
        Accept: application/json, text/javascript

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
    plan = plan.lower()
    check = check.lower()
    plans = copy_plans()
    current_plan = None
    for ex_plan in plans:
        if ex_plan['name'].lower() == plan:
            current_plan = ex_plan
            break
    else:
        return failed_response("No plan %s" % plan)
    if check in current_plan['checks']:
        return failed_response("Check %s is already in plan %s" % (check,
            plan))
    current_plan['checks'].append(check)
    upd, msg = update_plans(current_plan)
    if upd:
        return response_data(check=check, plan=plan)
    else:
        return failed_response(msg)


@app.route("/delete_check/<plan>/<check>")
def delete_check(plan, check):
    """Deletes check from given plan

    This method will be moved to global API

    :param string plan: plan to be changed
    :param string check: check to be removed from the plan

    **Example request**:

    .. sourcecode:: http

        GET /delete_check/professional/new
        Accept: application/json, text/javascript

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
    plan = plan.lower()
    check = check.lower()
    plans = copy_plans()
    current_plan = None
    for ex_plan in plans:
        if ex_plan['name'].lower() == plan:
            current_plan = ex_plan
            break
    else:
        return failed_response("No plan %s" % plan)
    check_plans = load_plans()
    if not check in check_plans[plan]:
        return failed_response("Check %s is not in plan %s" % (check, plan))
    if check in current_plan['checks']:
        current_plan['checks'].remove(check)
    else:
        if not 'exclude_checks' in current_plan:
            current_plan['exclude_checks'] = []
        current_plan['exclude_checks'].append(check)
    upd, msg = update_plans(current_plan)
    if upd:
        return response_data(check=check, plan=plan)
    else:
        return failed_response(msg)


def get_check_dir():
    config_dir = os.path.expanduser(app.config['XMD_CHECK_CONFIG_DIR'])
    return config_dir


def get_host_config(host):
    config_dir = get_check_dir()
    host_file = os.path.join(config_dir, app.config['XMD_HOST_CONFIG'].format(host=host))
    return host_file


def get_check_config():
    config_dir = get_check_dir()
    check_file = os.path.join(config_dir, app.config['XMD_CHECK_CONFIG'])
    if not os.path.exists(check_file):
        with open(check_file, 'w'):
            pass
    return check_file


def run(command):
    p = subprocess.Popen(shlex.split(command))
    return

def reload_cmk():
    run("cmk -R")

def inventory_host(host_name):
    run("cmk -R && cmk -I @%s" % host_name)

@app.route('/enable_host', methods=["POST"])
def enable_host():
    """Enable host in xmd

    add host to check_mk config

    :jsonparam string host: host to be enabled

    **Post data**:

    .. code-block:: json

        {
            "name": "Tests",
            "host": "10.10.10.10"
        }

    **Example request**:

    .. sourcecode:: http

        POST /enable_host
        Accept: application/json, text/javascript

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json
        {
          "status": "OK",
          "host": "10.10.10.10",
          "name": "Tests"
        }

    """
    data = request.json
    if data is None:
        return failed_response("No json data supplied")
    for key in ('host', 'name'):
        val = data.get(key)
        if val is None:
            return failed_response("No %s in request json" % key)
    host = data.get('host')
    name = data.get('name')
    config = get_host_config(host)
    if os.path.exists(config):
        return failed_response("Config already exists for host %s" % host)
    with open(config, 'w') as fp:
        fp.write('all_hosts += [ "%s"]\n' % name)
        fp.write('ipaddresses.update({"%s": "%s"})\n' % (name, host))
    inventory_host(name)
    return response_data(host=host, name=name)


@app.route('/disable_host', methods=["POST"])
def disable_host():
    """Disable host
    in the check_mk configuration

    :jsonparam string host: host to be disabled

    **Post data**:

    .. code-block:: json

        {
            "name": "Tests",
            "host": "10.10.10.10"
        }

    **Example request**:

    .. sourcecode:: http

        POST /disable_host
        Accept: application/json, text/javascript

    **Example response**:

    .. sourcecode:: http

        HTTP/1.1 200 OK
        Content-Type    application/json
        {
          "status": "OK",
          "host": "10.10.10.10"
        }

    """
    if request.json is None:
        return failed_response("No json data supplied")
    host = request.json.get('host')
    if host is None:
        return failed_response("No host in request json" )
    config = get_host_config(host)
    if not os.path.exists(config):
        return failed_response("Host %s does not exist" % host)
    try:
        os.remove(config)
    except OSError:
        return failed_response("Failed disabling host %s" % host)
    reload_cmk()
    return response_data(host=host)


@app.route('/enable_check', methods=["POST"])
@ensure_json(['name', 'item', 'params'])
def enable_check():
    """Enable check

    :jsonparam string name: check name
    :jsonparam string item: A check item or the keyword None for checks that do not need an item.
    :jsonparam string params: Paramters for the check or the keyword None for checks that do not need a parameter.
    """
    json_data = request.json
    add_checks([dict(
        name=json_data['name'],
        item=json_data['item'],
        params=json_data['params']
        )])
    reload_cmk()
    return response_data()


@app.route('/disable_check/<check>')
def disable_check(check):
    """Disable check

    :param string check: check to disable
    """
    delete_checks([check])
    reload_cmk()
    return response_data()


@app.route('/enable_checks', methods=["POST"])
@ensure_json(['checks'])
def enable_checks():
    """Enable list of checks

    :jsonparam list checks: list of dictionaries with checks to enable
    :jsonparam string checks['name']: check name
    :jsonparam string checks['item']: A check item or the keyword None for checks that do not need an item.
    :jsonparam string checks['params']: Paramters for the check or the keyword None for checks that do not need a parameter.
    """
    checks = request.json['checks']
    add_checks(checks)
    reload_cmk()
    return response_data()


@app.route('/disable_checks', methods=["POST"])
@ensure_json(['checks'])
def disable_checks():
    """Disable list of checks

    :jsonparam list checks: list of checks to enable
    """
    checks = request.json['checks']
    delete_checks(checks)
    reload_cmk()
    return response_data()


def add_checks(checks):
    check_file = get_check_config()
    checks_data = dict([(d['name'], d) for d in checks])
    with open(check_file, 'r+') as fp:
        for line in fp:
            curcheck_search = re.search('ALL_HOSTS, "(([^"])+)"', line)
            if not curcheck_search:
                continue
            curcheck = curcheck_search.groups()[0]
            if curcheck in checks_data:
                del checks[curcheck]
                continue
        for check in checks_data.values():
            fp.write(
                'checks += [(ALL_HOSTS, "%s", %s, %s),]\n' % (
                    check['name'],
                    check['item'],
                    check['params']
                    )
                )

def delete_checks(checks):
    check_file = get_check_config()
    with open(check_file, 'r+') as fp:
        old = ''
        for line in fp:
            curcheck_search = re.search('ALL_HOSTS, "(([^"])+)"', line)
            if not curcheck_search:
                old += line
                continue
            curcheck = curcheck_search.groups()[0]
            if curcheck in checks:
                continue
            old += line
        fp.seek(0)
        fp.truncate()
        fp.write(old)

if __name__ == '__main__':
    app.run(host=app.config['APP_HOST'], port=app.config['APP_PORT'])
