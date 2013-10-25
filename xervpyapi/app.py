import os
import json
from functools import wraps

from flask import Flask

from flask import jsonify, request
from werkzeug.exceptions import default_exceptions
from werkzeug.exceptions import HTTPException

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

    def make_json_response(response):
        import ipdb; ipdb.set_trace()

    app = Flask(import_name, **kwargs)
    app.after_this_request = make_json_response
    for code in default_exceptions.iterkeys():
        app.error_handler_spec[None][code] = make_json_error

    return app

app = make_json_app(__name__)

PROJECT_PATH = os.path.dirname(os.path.abspath(__file__))
DEBUG = True
TEST_JSON = os.path.join(PROJECT_PATH, 'test.json')

app.config.from_object(__name__)
app.config.from_pyfile(os.path.join(PROJECT_PATH, 'settings.py'))



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



def response_data(data={}, **kwargs):
    if kwargs:
        items = {}
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
    message = {
            'status': 'error',
            'message': 'Not Found: ' + request.url,
    }
    resp = jsonify(message)
    resp.status_code = 404
    return resp


@app.route('/get_templates')
def get_templates():
    plans = load_plans()
    return response_data(**plans)


@app.route('/get_template/<template>')
def get_template(template):
    plans = load_plans()
    plan = plans.get(template)
    if plan is None:
        return failed_response("No such plan %s" % template)
    return response_data(plan=plan, name=template)


@app.route('/add_template', methods=["POST"])
def add_template():
    plan = request.json
    upd, msg= update_plans(plan)
    if upd:
        return response_data(name=plan['name'])
    else:
        return failed_response("Could not add plan. %s" % msg if msg is not
                None else '')

@app.route("/add_check/<plan>/<check>")
def add_check(plan, check):
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


@app.route("/delete_from/<plan>/<check>")
def delete_check(plan, check):
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


def get_check_config(host, check):
    config_dir = get_check_dir()
    check_file = os.path.join(config_dir, app.config['XMD_CHECK_CONFIG'].format(check=check, host=host))
    return check_file


@app.route('/enable_host', methods=["POST"])
def enable_host():
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
        fp.write('ipdaresses.update({"%s": "%s"})\n' % (name, host))
    return response_data(host=host, name=name)


@app.route('/disable_host', methods=["POST"])
def disable_host():
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
    return response_data(host=host)


if __name__ == '__main__':
    app.run(host=app.config['APP_HOST'], port=app.config['APP_PORT'])
