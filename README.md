monitoting_api
==============

1.) Install OMD
2.)sudo apt-get install mailutils

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

proc = It must exactly match the first column of the agents output. Or - if the string is beginning with a tilde (~) - it is interpreted as a regular expression that must match the beginning of the process line as output by the agent.

Example: proc=/usr/bin/apache2 or proc=~apache

user = user that should be running the process. default is any user

warnmin = Minimum number of matched process for WARNING state (default is 1). This is the minimum amount of processes that will cause a WARNING.

okmin = Minimum number for OK state (default is 1). The amount of processes that need to be running for the check to be OK.

okmax = Maximum number for OK state (default is 1). This maximum amount of processes that will cause a check to be OK. Any more than this will cause CRITICAL.

warnmax = Maximum number for WARNING state (default is 1). The maximum amount of processes that will cause a WARNING.

Process counts more than warnmax will cause CRITICAL.

Process counts less than warnmin will cause CRITICAL.

Read more abou the ps check here: http://mathias-kettner.de/checkmk_check_ps.html

Example:

http://<server>/<site>/xervrest/add_proc_check?host=server01.example.com&cname=webserver&proc=~apache2&warnmin=1&okmin=3&okmax=10&warnmax=13

The API call above would add check called "webserver" for server01.example.com for a proc which contains the the string apache2 being run by any user.

The check would cause WARNING if there was only 1 single apache process and a WARNING if there were up to 13 processes. Anything between 3 and 10 would be OK.

Delete a check:
http://<server>/<site>/xervrest/del_proc_check?host=<host>&cname=<unique name>

List of xervrest checks added:
http://<server>/<site>/xervrest/host_proc_checks?host=<host>

Returns a json array containing the cnames of all the ps checks added via the REST API for the given host

Restart Nagios:
http://<server>/<site>/xervrest/restart_site

Will issue the check_mk -R command for the site.

Graphing and graphite:
----------------------
http://<server>/<site>/xervrest/get_graphite_url - return the graphite URL that is configured for the site.
http://<server>/<site>/xervrest/graph_name_map - list the mapping of service descriptions and their performance components.

Contacts:
---------

Adding:
http://<server>/<site>/xervrest/add_contact?contact_name=<name>

Mandatory parameter: contact_name
Other paramters: Defined in Nagios doc: http://nagios.sourceforge.net/docs/3_0/objectdefinitions.html#contact

Deleting:
http://<server>/<site>/xervrest/del_contact?contact_name=<name>

Note: To enable contacts you must call /restart_site so that the nagios config can be reloaded

Contact Groups:
---------------

http://<server>/<site>/xervrest/add_contact_group?contactgroup_name=<name>

Mandatory parameter: contactgroup_name
Other paramters: Defined in Nagios doc: http://nagios.sourceforge.net/docs/3_0/objectdefinitions.html#contactgroup

TAKE NOTE!!!! - Extra parameter: hosts - a comma seperated list of hosts that should be associated to the contact group (use ALL_HOSTS to specify all hosts)
Example /add_contact_group?hosts=server01,server02
Example /add_contact_group?hosts=ALL_HOSTS

Deleting:
http://<server>/<site>/xervrest/del_contact_group?contact_name=<name>

Note: To enable contacts you must call /restart_site so that the nagios config can be reloaded

Here is a shell script I wrote to test the contact/group methods
#!/bin/bash

USERNAME=omdadmin
PASSWORD=omd
SITE=c1
SERVER=ec2-54-242-185-242.compute-1.amazonaws.com
BASEURL=http://$SERVER/$SITE/xervrest

# Add 2 contacts
curl --user $USERNAME:$PASSWORD "$BASEURL/add_contact?contact_name=notify1&alias=NotifyContact1&host_notifications_enabled=1&service_notifications_enabled=1&service_notification_period=24x7&host_notification_period=24x7&service_notification_options=w,u,c,r&host_notification_options=d,u,r&service_notification_commands=check-mk-notify&host_notification_commands=check-mk-notify&email=notify1@kode.co.za"

curl --user $USERNAME:$PASSWORD "$BASEURL/add_contact?contact_name=notify2&alias=NotifyContact2&host_notifications_enabled=1&service_notifications_enabled=1&service_notification_period=24x7&host_notification_period=24x7&service_notification_options=w,u,c,r&host_notification_options=d,u,r&service_notification_commands=check-mk-notify&host_notification_commands=check-mk-notify&email=notify2@kode.co.za"

# Add contact groups
curl --user $USERNAME:$PASSWORD "$BASEURL/add_contact_group?contactgroup_name=notifygroup1&alias=NotifyGroup1&members=notify1,notify2&hosts=server.kode.co.za"

curl --user $USERNAME:$PASSWORD "$BASEURL/add_contact_group?contactgroup_name=notifygroup2&alias=NotifyGroup2&members=notify1,notify2&hosts=ALL_HOSTS"

# Restart site
curl --user $USERNAME:$PASSWORD "$BASEURL/restart_site"
