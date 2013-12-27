APP_PORT = 5000
APP_HOST = '127.0.0.1'
SECRET_KEY = 'development'

MONGODB_SETTINGS = {
        "DB": "xervglobal",
        "HOST": "localhost",
        "PORT": 27017,
        "USERNAME": "",
        "PASSWORD": ""
        }

CELERY_BROKER_URL='redis://localhost:6379',
CELERY_RESULT_BACKEND='redis://localhost:6379'


XMD_CHECK_CONFIG_DIR = '~/etc/check_mk/conf.d/'
XMD_HOST_CONFIG = 'xervmon_host_{host}.mk'
XMD_CHECK_CONFIG = 'xervmon_checks.mk'

SITE_API_URL = 'http://192.241.201.21/{sitename}/xervpyapi/'
