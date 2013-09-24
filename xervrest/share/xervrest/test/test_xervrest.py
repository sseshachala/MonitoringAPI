
import urllib2
import json

host = 'ec2-54-242-185-242.compute-1.amazonaws.com'
site = 'c1'
username = 'omdadmin'
password = 'omd'
 
base_url = 'http://%s/%s/xervrest' % (host, site)

# Handle authentication
auth_handler = urllib2.HTTPBasicAuthHandler()
auth_handler.add_password(realm='OMD Monitoring Site %s' % site, uri=base_url, user=username, passwd=password)
#auth_handler.add_password(uri=base_url, user=username, passwd=password)
opener = urllib2.build_opener(auth_handler)
urllib2.install_opener(opener)

# First batch
urls = {
    'Hosts - all': {'url': '/hosts', 'params': []},
    'Host groups - all': {'url': '/hostgroups', 'params': []},
    'Hosts by group - all': {'url': '/hostsbygroup', 'params': []},
    'Services - all': {'url': '/services', 'params': []},    
    'Service groups - all': {'url': '/servicegroups', 'params': []},
    'Services by group - all': {'url': '/servicesbygroup', 'params': []},
    'Services by host group - all': {'url': '/servicesbyhostgroup', 'params': []},
    'Contacts - all': {'url': '/contacts', 'params': []},
    'Contact groups - all': {'url': '/contactgroups', 'params': []},
    'Commands - all': {'url': '/commands', 'params': []},
    'Timeperiods - all': {'url': '/timeperiods', 'params': []},
    'Downtimes - all': {'url': '/downtimes', 'params': []},
    'Comments - all': {'url': '/comments', 'params': []},
    'Log - all': {'url': '/log', 'params': []},
    'Status - all': {'url': '/status', 'params': []},
    'columns - all': {'url': '/columns', 'params': []},
    'State hist - all': {'url': '/statehist', 'params': []},
}

for url in urls:
    print "Test: %s" % url
    full_url =  base_url + urls[url]['url']
    print "URL: %s" % full_url
    
    try:
        fh = urllib2.urlopen(full_url)
    except urllib2.HTTPError, e:
        print "FAIL: Received an HTTP error. %s" % e
        continue
        
    raw_result = fh.read()
    try:
        result = json.loads(raw_result)
    except:
        print "FAIL: Could not load JSON output"
    else:
        print "PASS: No HTTP errors. Valid JSON." % result['response']
    print "--------------------------------------------"
        