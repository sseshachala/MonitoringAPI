monitoting_api
==============

1.) Install OMD

2.) Untar zervrest.tgz

3.) Create site

4.) Start site

5.) Add hosts

Brief API documentation

List of 'GET' methods
----------------------
http://<server>/<site>/xervrest/hosts

http://<server>/<site>/xervrest/services

http://<server>/<site>/xervrest/hostgroups

http://<server>/<site>/xervrest/servicegroups

http://<server>/<site>/xervrest/contactgroups

http://<server>/<site>/xervrest/servicesbygroup

http://<server>/<site>/xervrest/servicesbyhostgroup

http://<server>/<site>/xervrest/hostsbygroup

http://<server>/<site>/xervrest/contacts

http://<server>/<site>/xervrest/commands

http://<server>/<site>/xervrest/timeperiods

http://<server>/<site>/xervrest/downtimes

http://<server>/<site>/xervrest/comments

http://<server>/<site>/xervrest/log

http://<server>/<site>/xervrest/status

http://<server>/<site>/xervrest/columns

http://<server>/<site>/xervrest/statehist

Columns & Filters:
------------------

http://<server>/<site>/xervrest/hosts?columns=<col>,<col>,<col>

http://<server>/<site>/xervrest/hosts?filter0=<filter>&filter1=<filter>

Limit & Offset:
---------------

http://<server>/<site>/xervrest/hosts?limit=<n>&offset=<n>


List of 'ACTION' methods
------------------------
http://<server>/<site>/xervrest/ack_host?host=<hostname>&message=<message>

http://<server>/<site>/xervrest/ack_service?ack_service=<servername>&message=<message>

http://<server>/<site>/xervrest/schedule_host_check?host=<hostname>

http://<server>/<site>/xervrest/schedule_host_services_check?host=<hostname>

http://<server>/<site>/xervrest/schedule_service_check?host=<hostname>&service=<servername>

http://<server>/<site>/xervrest/delete_comment?comment_id=<comment_id>

http://<server>/<site>/xervrest/remove_host_acknowledgement?host=<hostname>

Process Methods
---------------

Added a process check:
http://<server>/<site>/xervrest/add_proc_check?host=<host>&cname=<unique name>&proc=/usr/bin/myproc&user=<user>&warnmin=<n>&okmin=<n>&okmax=<n>&warnmax=<n>

host = host name as in check_mk
cname = A unique name for the check. Example: apache
proc = the name of the process (can be a wildcard as per the check_mk docs). E.g. /usr/sbin/apache
user = user that should be running the process. default is any user
warnmin = See http://mathias-kettner.de/checkmk_check_ps.html
okmin = See http://mathias-kettner.de/checkmk_check_ps.html
okmax = See http://mathias-kettner.de/checkmk_check_ps.html
warnmax = See http://mathias-kettner.de/checkmk_check_ps.html

Delete a check:
http://<server>/<site>/xervrest/del_proc_check?host=<host>&cname=<unique name>

List of xervrest checks added:
http://<server>/<site>/xervrest/host_proc_checks?host=<host>

Returns a json array containing the cnames of all the ps checks added via the REST API for the given host

Restart Nagios:
http://<server>/<site>/xervrest/restart_site

Will issue the check_mk -R command for the site.
