
import json
import unittest
import urllib
import urllib2
import sys

class TestRestApi(unittest.TestCase):
    # Configuration Starts #
    site = 'c1'
    host = 'localhost'
    username = 'omdadmin'
    password = 'omd'
    # Configuration Ends #
    
    base_url = 'http://%s/%s/xervrest'
    
    def setUp(self):
        self.base_url = self.base_url % (self.host, self.site)
    
        # Handle authentication
        auth_handler = urllib2.HTTPBasicAuthHandler()
        auth_handler.add_password(realm='OMD Monitoring Site %s' % self.site, uri=self.base_url, user=self.username, passwd=self.password)
        opener = urllib2.build_opener(auth_handler)
        urllib2.install_opener(opener)

    def _do_http_request(self, url, params=None):
        if params is not None:
            url = '%s?%s' % (url, urllib.urlencode(params))    
        fh = urllib2.urlopen(url)
        return fh.read()
    
    def _is_json(self, thing):
        try:
            result = json.loads(thing)
        except:
            return False
        return True

    def _is_error_response(self, results):
        if 'response' in results:
            if results['response'] == 'error':
                return results['message']
        return False

    def test_add_hosts(self):
        url = '%s/%s' % (self.base_url, 'add_hosts')
        params = {'hostname_1': 'www.google.com', 'ip_1': '173.194.73.104', 'hostname_2': 'www.amazon.com', 'ip_2': '176.32.98.166'}

        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))

        results = json.loads(raw_result)
        self.assertFalse(self._is_error_response(results))
        return results

    def test_del_hosts(self):
        url = '%s/%s' % (self.base_url, 'del_hosts')
        params = {'ip_1': '173.194.73.104', 'ip_2': '176.32.98.166'}

        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))

        results = json.loads(raw_result)
        self.assertFalse(self._is_error_response(results))
        return results

    def test_get_all_hosts(self):
        url = '%s/%s' % (self.base_url, 'hosts')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'hosts' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
            
        return results
    
    def test_limit(self):
        url = '%s/%s' % (self.base_url, 'hosts')
        params = {'columns': 'name', 'limit': 1}
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'hosts' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results[1]), 1)
    
    def test_offset(self):
        url = '%s/%s' % (self.base_url, 'hosts')
        params = {'columns': 'name'}
        
        try:
            raw_result_1 = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result_1) is False:
            self.fail("Method 'hosts' did not return valid JSON.")
        
        results = json.loads(raw_result_1)
        
        self.assertFalse(self._is_error_response(results))
        
        if not len(results) < 2:
            self.skipTest("Too few hosts to test pagination 'offset' parameter.")
        
        params.update({'limit':1, 'offset': 1})
        
        try:
            raw_result_2 = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        results = json.loads(raw_result_2)
        
        self.assertFalse(self._is_error_response(results))
        
        self.assertEqual(len(results[1]), 1)
        
    def test_filter(self):
        url = '%s/%s' % (self.base_url, 'hosts')
        params = {'columns': 'name'}
        
        try:
            raw_result_1 = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result_1) is False:
            self.fail("Method 'hosts' did not return valid JSON.")
        
        results = json.loads(raw_result_1)
        
        self.assertFalse(self._is_error_response(results))
        
        test_host = results[1][0]
        
        if not test_host:
            self.fail("Could not extract a single host from /hosts")
        
        params.update({'filter0':'name=%s' % test_host})
        
        try:
            raw_result_2 = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        results = json.loads(raw_result_2)
        
        self.assertFalse(self._is_error_response(results))
        self.assertEqual(test_host, results[1][0])
        
    def test_get_all_hostgroups(self):
        url = '%s/%s' % (self.base_url, 'hostgroups')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'hostgroups' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_hostsbygroup(self):
        url = '%s/%s' % (self.base_url, 'hostsbygroup')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'hostsbygroup' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_services(self):
        url = '%s/%s' % (self.base_url, 'services')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'services' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_servicegroups(self):
        url = '%s/%s' % (self.base_url, 'servicegroups')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'servicegroups' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_servicesbygroup(self):
        url = '%s/%s' % (self.base_url, 'servicesbygroup')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'servicesbygroup' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_servicesbyhostgroup(self):
        url = '%s/%s' % (self.base_url, 'servicesbyhostgroup')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'servicesbyhostgroup' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)


    def test_get_all_contacts(self):
        url = '%s/%s' % (self.base_url, 'contacts')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'contacts' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_contactgroups(self):
        url = '%s/%s' % (self.base_url, 'contactgroups')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'contactgroups' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_commands(self):
        url = '%s/%s' % (self.base_url, 'commands')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'commands' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_timeperiods(self):
        url = '%s/%s' % (self.base_url, 'timeperiods')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'timeperiods' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)


    def test_get_all_downtimes(self):
        url = '%s/%s' % (self.base_url, 'downtimes')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'downtimes' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)
    
    def test_get_all_comments(self):
        url = '%s/%s' % (self.base_url, 'comments')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'comments' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_log(self):
        url = '%s/%s' % (self.base_url, 'log')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'log' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def test_get_all_columns(self):
        url = '%s/%s' % (self.base_url, 'columns')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'columns' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def X_test_get_all_statehist(self):
        url = '%s/%s' % (self.base_url, 'statehist')
        params = None
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'statehist' did not return valid JSON.")
        
        results = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(results))
        self.assertGreaterEqual(len(results), 1)

    def _get_single_host(self):
        url = '%s/%s' % (self.base_url, 'hosts')
        params = {'columns': 'name', 'limit': 1}
        raw_result = self._do_http_request(url, params=params)
        results = json.loads(raw_result)
        return results[1][0]
    
    def test_add_proc_check(self):
        try:
            host = self._get_single_host()        
        except urllib2.HTTPError, e:
            self.skipTest("Could not get a host name. HTTP Error. error=%s" % e)
        except ValueError, e:
            self.skipTest("Could not get a host name. JSON Error. error=%s" % e)
        except Exception, e:
            self.skipTest("Could not get a host name. Unknown Error. error=%s" % e)
        
        url = '%s/%s' % (self.base_url, 'add_proc_check')
        params = {'host': host, 'cname': 'python-unittest', 'proc': '~apache2', 'user': self.site, 
                    'warnmin': 1, 'okmin': 3, 'warnmax': 2, 'okmax': 6 }        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'add_proc_check' did not return valid JSON.")
        
        res = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(res))
        
        url = '%s/%s' % (self.base_url, 'host_proc_checks')
        params = {'host': host}
        
        try:
            raw_result = self._do_http_request(url, params=params)
        except urllib2.HTTPError, e:
            self.fail("HTTP Error. url=%s error=%s" % (url, e))
        
        if self._is_json(raw_result) is False:
            self.fail("Method 'host_proc_checks' did not return valid JSON.")
        
        res = json.loads(raw_result)
        
        self.assertFalse(self._is_error_response(res))
        self.assertTrue('python-unittest' in res)
        
if __name__ == '__main__':
    unittest.main()

