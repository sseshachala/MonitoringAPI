all_hosts += [ "development|cmk_agent|vm|linux|apache|xervrest|/" + FOLDER_PATH + "/"]
ipaddresses.update({'development': u'162.243.217.62'})
host_attributes.update({'development': {'ipaddress': u'162.243.217.62', 'tag_agent' : 'cmk_agent','tag_server' : 'vm','tag_system' : 'linux'}})
checks += [( 'development', 'ps.perf', 'apache', ('/usr/sbin/apache2', 1, 1, 20, 30 ))]
checks += [ ( 'development', 'logwatch', '/var/log/apache2/error.log', '') ]
ipaddresses.update({'development': u'162.243.217.62'})
host_attributes.update({'development': {'ipaddress': u'162.243.217.62', 'tag_app1' : 'apache'}})
