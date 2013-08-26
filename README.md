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
