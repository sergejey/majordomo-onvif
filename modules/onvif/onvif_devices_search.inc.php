<?php
/*
* @version 0.1 (wizard)
*/
 global $session;
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $qry="1";
  // search filters
  // QUERY READY
  global $save_qry;
  if ($save_qry) {
   $qry=$session->data['onvif_devices_qry'];
  } else {
   $session->data['onvif_devices_qry']=$qry;
  }
  if (!$qry) $qry="1";
  $sortby_onvif_devices="ID DESC";
  $out['SORTBY']=$sortby_onvif_devices;
  // SEARCH RESULTS
  $res=SQLSelect("SELECT * FROM onvif_devices WHERE $qry ORDER BY ".$sortby_onvif_devices);
  if ($res[0]['ID']) {
   //paging($res, 100, $out); // search result paging
   $total=count($res);
   for($i=0;$i<$total;$i++) {
    // some action for every record if required
   }
   $out['RESULT']=$res;
  }
