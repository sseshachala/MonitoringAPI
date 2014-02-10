#!/usr/bin/python

import argparse
import datetime
import json
import logging
import os, os.path
import paramiko
import re
import sys

def handle_args():
    parser = argparse.ArgumentParser(description='Install OMD agent on linux server.')
    parser.add_argument('--action', action='store', default='install', help='What to do? Options: install, report Default: install')
    parser.add_argument('--pid', action='store', default=None, help='PID of a previous install process.')
    parser.add_argument('--host', action='store', default=False, help='Target server host name.', required=True)
    parser.add_argument('--port', action='store', default=22, help='SSH port.')
    parser.add_argument('--username', action='store', default=None, help='User.')
    parser.add_argument('--password', action='store', default=None, help='Password.')
    parser.add_argument('--key', action='store', default=None, help='SSH key.')
    parser.add_argument('--config', action='store', default=False, help='Config file.', required=True)
    args = parser.parse_args()
    return args

def parse_config(file):
    fh = open(file, 'r')
    config = fh.read()
    fh.close()

    if config:
        return json.loads(config)
    else:
        return json.loads('[]')

class OMDInstaller:
    args = None
    config = None
    client = None
    now = None
    output_file = None

    def _setup_logging(self):
        levels = { 'INFO': logging.INFO, 'DEBUG': logging.DEBUG }
        _format = '%(asctime)s %(levelname)s %(message)s'
        logfile = self.config['log_file']
        loglevel = levels[self.config['log_level']]
        logging.basicConfig(filename=logfile,level=loglevel, format=_format)

    def __init__(self, args, config):
        self.args = args
        self.config = config
        self._setup_logging()
        logging.info("args=%s" % args)
        logging.info("config=%s" % config)

        self.now = datetime.datetime.now().strftime("%d%m%y-%H%M")
        self.output_file = '%s/%s-%s.output' % (self.config['command_output_dir'], self.args.host, self.now)
        logging.info("now=%s" % self.now)
        logging.info("output_file=%s" % self.output_file)

    def connect(self):
        try:
            self.client = paramiko.SSHClient()
            self.client.load_system_host_keys()
            self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            self.client.connect(self.args.host, port=self.args.port, username=self.args.username, password=self.args.password, key_filename=self.args.key)
        except paramiko.AuthenticationException, e:
            logging.info("FATAL. Connection failed. Incorrect username and/or password. %s" % e)
            raise
        except Exception, e:
            logging.info("FATAL. Connection failed. %s" % e)
            raise

        logging.info("Connected to %s:%s" % (self.args.host, self.args.port))

    def _store_output(self, command, stdout, stderr):
        fh = open(self.output_file, 'a')
        now = datetime.datetime.now().strftime("%d/%m/%y %H:%M:%S")
        fh.write("%s: %s\nSTDOUT:\n" % (now,command))
        fh.write(stdout)

        if stderr:
            fh.write("\nSTDERR:\n")
            fh.write(stderr)

        fh.write("\nEND\n")
        fh.close()
    
    def get_install_log(self):
        (_out, _err) = self.run_command("cat omd-agent-installer.log")
        return(_out, _err)
        
    def run_command(self, command):
        stdin, stdout, stderr = self.client.exec_command(command)
        stdout = stdout.read()
        stderr = stderr.read()

        if self.config['store_output'] > 0:
            self._store_output(command, stdout, stderr)

        return(stdout, stderr)

    def get_os(self):
        (_out, _err) = self.run_command("cat /etc/*release")

        if re.search('(debian|ubuntu)', _out, re.MULTILINE|re.IGNORECASE) is not None:
            return 'deb'

        if re.search('(red hat|centos)', _out, re.MULTILINE|re.IGNORECASE) is not None:
            return 'rpm'
        
        return None

    def upload_file(self, local, remote):
        sftp = self.client.open_sftp()
        sftp.put(local, remote)
        logging.info("Uploaded %s to %s:%s" % (local, self.args.host, remote))

    def install_package(self, os, package_file):
        cmd = 'echo $$; exec nohup '
        
        if args.username != 'root': cmd += 'sudo '
        
        opts = ''
        if os == 'deb':
            cmd += 'dpkg %s -i %s > omd-agent-installer.log 2>&1 &' % (opts, package_file)
            
        if os == 'rpm': 
            cmd += 'rpm -i %s %s > omd-agent-installer.log 2>&1 &' % (opts, package_file)
        
        _out, _err = self.run_command(cmd)
        return _out        

    def upload_plugins(self, plugins, dest_dir):
        for plugin in plugins:
            dest = '%s/%s' % (dest_dir, os.path.basename(plugin))
            self.upload_file(plugin, dest)
            
    def package_is_installed(self, os):
        if os == 'deb': 
            (_out, _err) = self.run_command('dpkg -s check-mk-agent | grep Status:')
            if re.search('install ok installed', _out) is not None:
                return True
        
        if os == 'rpm': 
            (_out, _err) = self.run_command('rpm -qa| grep check_mk-agent')
            if re.search('check_mk-agent', _out) is not None:
                return True
                
    def installer_is_running(self, os, pid):
        if os == 'deb': regex = 'dpkg -i'
        if os == 'rpm': regex = 'rpm -i'
        cmd = '/bin/ps --no-heading -p %s' % pid
        (_out, _err) = self.run_command(cmd)
        
        if _out:
            if re.search(regex, _out, re.MULTILINE) is None: return False
        else:
            return False
        return True
        
    def close(self):
        self.client.close()

if __name__ == '__main__':
    args =  handle_args()
    config = parse_config(args.config)

    try:
        installer = OMDInstaller(args, config)
        installer.connect()
        _os = installer.get_os()
    except Exception:
        print '{ "response": "failure", "Could not connect to host %s - see logs for details." }' % args.host
        sys.exit()
        
    if _os is None:
        logging.info("ERROR - OS not supported. Only Debian, Ubuntu, Red Hat and Centos.")
        # TODO output must be JSON
        print "ERROR!!! OS not supported. Only Debian, Ubuntu, Red Hat and Centos"
        installer.close()
        sys.exit(1)
    
    package_file = installer.config[_os + '_agent'] 
    
    if args.action == 'install':
        installer.upload_file(package_file, os.path.basename(package_file))
        remote_pid = installer.install_package(_os, os.path.basename(package_file))
        
        if 'plugins' in config:
            installer.upload_plugins(config['plugins'], '/usr/lib/check_mk_agent/plugins')
        
        print '{ "pid": "%s" }' % remote_pid.rstrip()

    if args.action == 'report':
        if installer.package_is_installed(_os):
            print '{ "response": "success", "message": "Agent package installed." }'
            installer.close()
            sys.exit()
            
        is_running = False
        log, _err = installer.get_install_log()
        
        if args.pid is not None:
            is_running = installer.installer_is_running(_os, args.pid)
            
        if is_running is True:
            print '{ "response": "pending", "message": "Installer is still running." }'
            installer.close()
            sys.exit()
        else:
            if re.search("Setting up check-mk-agent", log, re.MULTILINE|re.IGNORECASE) is None:
                print '{ "response": "failure", "OMD agent has *NOT* been installed. Log follows: %s" }' % log.rstrip()
            else:
                print '{ "response": "unknown", "OMD agent has *possibly* been installed. Log follows: %s" }' % log.rstrip()
    
    installer.close()
