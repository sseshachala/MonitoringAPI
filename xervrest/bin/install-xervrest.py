

import argparse
import os

def handle_args():
    parser = argparse.ArgumentParser(description='Install XervRest into OMD sites.')
    parser.add_argument('site', metavar='SITE', help='OMD Site to install into.')
    parser.add_argument('--all', action='store_true', default=False, help='Install for all existing sites.')
    args = parser.parse_args()
    return args

def root_dir():
    return '/omd/sites';

def get_all_sites():
    return os.listdir(root_dir())

def get_uninstalled_sites():
    for site in get_all_sites():
        if not os.path.isfile("%s/%s/etc/apache/conf.d/0xervrest.conf" % (root_dir(), site)):
            yield site

def site_is_uninstalled(site):
    if os.path.isfile("%s/%s/etc/apache/conf.d/0xervrest.conf" % (root_dir(), site)): return True
    return False

def install_site(site):
    

for site in get_uninstalled_sites():
    print site
