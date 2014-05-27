#!/usr/bin/python
# -*- coding: UTF-8 -*-

import os
import sys
import pwd
import grp
import subprocess
import shutil
import pwd
from subprocess import Popen, PIPE
from flask import Flask
from flask import request
from flask import json
from flask import jsonify
app = Flask(__name__)

@app.route('/')
def hello_world():
	return 'XMD Service API'

@app.route('/xmdstart', methods=['POST'])
def xmd_start():
	req_json = request.get_json()

	site_id = req_json['site']
	#os.system('omd start '+site_id)
	try:
		output = subprocess.check_output('omd start '+site_id, stderr=subprocess.STDOUT, shell=True)
	except subprocess.CalledProcessError as e:
		return jsonify(status='ERROR', message='Can not start site', details=e.output)
	
	return jsonify(status='Ok', message='Site started',details=output)


@app.route('/xmdrestart', methods=['POST'])
def xmd_restart():
	req_json = request.get_json()

	site_id = req_json['site']
	try:
		output = subprocess.check_output('omd restart '+site_id, stderr=subprocess.STDOUT, shell=True)
	except subprocess.CalledProcessError as e:
		return jsonify(status='ERROR', message='Can not restart site', details=e.output)
	
	return jsonify(status='Ok', message='Site restarted',details=output)


@app.route('/xmdstop', methods=['POST'])
def xmd_stop():
	req_json = request.get_json()

	site_id = req_json['site']
	#os.system('omd stop '+site_id)
	try:
		output = subprocess.check_output('omd stop '+site_id, stderr=subprocess.STDOUT, shell=True)
	except subprocess.CalledProcessError as e:
		return jsonify(status='ERROR', message='Can not stop site', details=e.output)
	
	return jsonify(status='Ok', message='Site stopped',details=output)


@app.route('/xmdcreate', methods=['POST'])
def xmd_create():
	req_json = request.get_json()

	site_id = req_json['site']
	try:
		output = subprocess.check_output('omd create '+site_id, stderr=subprocess.STDOUT, shell=True)
	except subprocess.CalledProcessError as e:
		return jsonify(status='ERROR', message='Can not create site', details=e.output)
	
	return jsonify(status='Ok', message='Site created',details=output)

@app.route('/xmdcopy', methods=['POST'])
def xmd_copy():
	req_json = request.get_json()

	site_id_src = req_json['sitesrc']
	site_id_dst = req_json['sitedst']
	try:
		output = subprocess.check_output('omd cp '+site_id_src+' '+site_id_dst, stderr=subprocess.STDOUT, shell=True)
	except subprocess.CalledProcessError as e:
		return jsonify(status='ERROR', message='Can not copy site', details=e.output)
	
	return jsonify(status='Ok', message='Site copied',details=output)


@app.route('/xmdstatus', methods=['POST'])
def xmd_status():
	req_json = request.get_json()

	site_id = req_json['site']
	try:
		output = subprocess.check_output('omd status '+site_id, stderr=subprocess.STDOUT, shell=True)
	except subprocess.CalledProcessError as e:
		return jsonify(status='ERROR', message='Can not get status of site', details=e.output)
	
	return jsonify(status='Ok', message='Site status',details=output)

@app.route('/xmdinventory', methods=['POST'])
def xmd_inventory():
	req_json = request.get_json()

	site_id = req_json['site']
	try:
		output = subprocess.check_output("sudo su - "+site_id+" -c 'cmk -I'", shell=True)
	except:
		return jsonify(status='ERROR', message='Can not run inventory for site', details=output)
	return jsonify(status='Ok', message='Invenory updated',details=output)	


@app.route('/xmdenginerestart', methods=['POST'])
def xmd_engine_restart():
	req_json = request.get_json()

	site_id = req_json['site']
	try:
		output = subprocess.check_output("sudo su - "+site_id+" -c 'cmk -R'", stderr=subprocess.STDOUT, shell=True)
	except:
		return jsonify(status='ERROR', message='Can not restart engine site', stderr=subprocess.STDOUT, details=output)
	return jsonify(status='Ok', message='Engine restarted',details=output)

@app.route('/configcopy', methods=['POST'])
def xmd_configcopy():
	req_json = request.get_json()

	#site_id = req_json['site']
	config_path = req_json['configpath']
	config_body = req_json['configbody']
	try:
		with open(config_path, "w") as config_file:
			config_file.write(config_body)
	except:
		return jsonify(status='ERROR', message='Can not save config')
	return jsonify(status='Ok', message='Config saved')


if __name__ == '__main__':
	app.run(debug=True)
