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

def load_plans():
    with open(TEST_JSON) as fp:
        data = json.load(fp)
    plans = {}
    for plan in data['plans']:
        if not plan['active']:
            continue
        checks = set(plan['checks'])
        plans[plan['name']] = checks
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
            name = plan['name']
            plans[name] = plans[name].union(incl_checks)
    return plans


def update_plans(plan):
    pass


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
    print request.data
    plan = {}
    update_plans(plan)


@app.route('/add_host')
def add_host():
    pass


@app.route('/enable_host')
def enable_host():
    pass


@app.route('/')
def main():
    return response_data({'data':'asg' })


if __name__ == '__main__':
    app.run()
