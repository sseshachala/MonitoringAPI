 <?php
 interface API
 {
 	/**
 	 * Authorize the connect
 	 */
 	public function connect($params);
 	
 	public function getHosts();
 	
 	public function getHost($params);
 	
 	public function systemStatusJson();
 	
 	public function getServiceGroups();
 
 	
 }
 
 ?>