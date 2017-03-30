<?php
/**
* ONVIF 
* @package project
* @author Wizard <sergejey@gmail.com>
* @copyright http://majordomo.smartliving.ru/ (c)
* @version 0.1 (wizard, 15:02:26 [Feb 24, 2017])
 * https://github.com/ltoscano/ponvif
 * https://www.onvif.org/onvif/ver10/events/wsdl/event.wsdl
 * https://altaroca.wordpress.com/2014/09/13/onvif-notifications/
 * https://github.com/jimxl/ruby-onvif-client/blob/master/lib/ruby_onvif_client/event_handing/subscribe.rb
 * https://github.com/altaroca/ZoneMinder/blob/onvif/onvif/scripts/zmonvif-trigger.pl
*/
//
//
class onvif extends module {
/**
* onvif
*
* Module class constructor
*
* @access private
*/
function onvif() {
  $this->name="onvif";
  $this->title="ONVIF";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->data_source)) {
  $p["data_source"]=$this->data_source;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $data_source;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($data_source)) {
   $this->data_source=$data_source;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {

  include_once(DIR_MODULES.'onvif/class.ponvif.php');
  $this->onvif=new Ponvif();

  global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['DATA_SOURCE']=$this->data_source;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}

/**
* Title
*
* Description
*
* @access public
*/
 function discovery() {
  $result = $this->onvif->discover();

  if (is_array($result)) {
   $total=count($result);
   for($i=0;$i<$total;$i++) {
    if (!$result[$i]['IPAddr']) {
     continue;
    }
    $rec=SQLSelectOne("SELECT * FROM onvif_devices WHERE (IP LIKE '".DBSafe($result[$i]['IPAddr'])."' OR XADDRS LIKE '".DBSafe($result[$i]['XAddrs'])."')");
    $rec['ENDPOINT_ADDRESS']=trim($result[$i]['EndpointReference']['Address']);
    $rec['IP']=$result[$i]['IPAddr'];
    $rec['TYPES']=$result[$i]['Types'];
    $rec['XADDRS']=$result[$i]['XAddrs'];
    $rec['SCOPES']=$result[$i]['Scopes'];
    if (!$rec['TITLE']) {
     $rec['TITLE']=$rec['ENDPOINT_ADDRESS'];
    }
    if (!$rec['ID']) {
     $rec['ID']=SQLInsert('onvif_devices', $rec);
    } else {
     SQLUpdate('onvif_devices', $rec);
    }
    $this->updateDevice($rec['ID'],$this->onvif);

   }
  }
 }


 function updateDevice($id, &$onvif_object, $quick=0) {
     $rec=SQLSelectOne("SELECT * FROM onvif_devices WHERE ID=".(int)$id);
     $onvif_object->setIPAddress($rec['IP']);
     if ($rec['USERNAME']) {
         $onvif_object->setUsername($rec['USERNAME']);
     } else {
         $onvif_object->setUsername('');
     }
     if ($rec['PASSWORD']) {
         $onvif_object->setPassword($rec['PASSWORD']);
     } else {
         $onvif_object->setPassword('');
     }

     if ($rec['XADDRS']) {
         $onvif_object->setMediaUri($rec['XADDRS']);
     } else {
         $onvif_object->setMediaUri('');
     }

     if (!$rec['ENDPOINT_ADDRESS'] && !$rec['TYPES'] && !$rec['XADDRS']) {
         //new device
         //$response=$onvif_object->core_GetDeviceInformation();
         //$response=$onvif_object->core_GetCapabilities();
         //var_dump($response);exit;
     }


    $initialized=$onvif_object->initialize();
     if (!$rec['ENDPOINT_ADDRESS'] && !$rec['TYPES'] && !$rec['XADDRS']) {
         //var_dump($onvif_object);exit;
     }

    if ($initialized) {

        if (!$quick) {

            $sources = $onvif_object->getSources();
            $streams = array();
            $total = count($sources[0]);
            $seen = array();
            for ($i = 0; $i < $total; $i++) {
                $profileToken = $sources[0][$i]['profiletoken'];
                if (!$profileToken) {
                 continue;
                }
                $mediaUri = $onvif_object->media_GetStreamUri($profileToken);
                if (!$seen[$mediaUri]) {
                    $seen[$mediaUri] = 1;
                    $streams[] = $mediaUri;
                }
            }
            $total = count($streams);
            if ($total > 0) {
                SQLExec("DELETE FROM onvif_streams WHERE DEVICE_ID=" . $rec['ID']);
                for ($i = 0; $i < $total; $i++) {
                    $stream = array();
                    $stream['TITLE'] = 'Stream ' . ($i + 1);
                    $stream['URL'] = $streams[$i];
                    $stream['DEVICE_ID'] = $rec['ID'];
                    SQLInsert('onvif_streams', $stream);
                }
            }
        }

        if ($rec['SUBSCRIBE']) {
            $response = $onvif_object->events_Subscribe();
            if ($response['SubscriptionReference']['Address']) {
                $rec['SUBSCRIPTION_ADDRESS']=$response['SubscriptionReference']['Address'];
                SQLUpdate('onvif_devices',$rec);
            }
            /*
            if ($rec['SUBSCRIPTION_ADDRESS']) {
                $response=$onvif_object->events_Pull($rec['SUBSCRIPTION_ADDRESS']);
                $this->processEventResponse($rec['ID'],$response);
            }
            */
        }
        return 1;
    } else {
     return 0;
    }
  
 }

function processEventResponse($device_id,$data) {
    $rec=SQLSelectOne("SELECT * FROM onvif_devices WHERE ID=".(int)$device_id);
    if (!$rec['ID']) return;

    //DebMes("Processing event response for $device_id : \n".json_encode($data));
    $items=array();

    $item=array();
    $item['TOPIC']=$data['NotificationMessage']['Topic'];
    $item['FULL_DATA']=$data['NotificationMessage']['Message']['Message'];
    $item['NAME']=$data['NotificationMessage']['Message']['Message']['Data']['SimpleItem']['@attributes']['Name'];
    $item['TITLE']=$item['TOPIC'].'/'.$item['NAME'];
    $value=$data['NotificationMessage']['Message']['Message']['Data']['SimpleItem']['@attributes']['Value'];
    if ($value=='false') {
        $value=0;
    } elseif ($value=='true') {
        $value=1;
    }
    $item['VALUE']=$value;

    $items[]=$item;

    foreach($items as $item) {
        $command=SQLSelectOne("SELECT * FROM onvif_commands WHERE DEVICE_ID=".$rec['ID']." AND TITLE LIKE '".DBSafe($item['TITLE'])."'");
        if (!$command['ID']) {
            $command['TITLE']=$item['TITLE'];
            $command['DEVICE_ID']=$rec['ID'];
            $command['ID']=SQLInsert('onvif_commands',$command);
        }
        $old_value=$command['VALUE'];
        $value=$command['VALUE'];
        $command['VALUE']=$item['VALUE'];
        $command['UPDATED']=date('Y-m-d H:i:s');
        SQLUpdate('onvif_commands',$command);
        if ($command['LINKED_OBJECT'] && $command['LINKED_PROPERTY']) {
            setGlobal($cmd_rec['LINKED_OBJECT'].'.'.$cmd_rec['LINKED_PROPERTY'], $value, array($this->name=>'0'));
        }
        if ($command['LINKED_OBJECT'] && $command['LINKED_METHOD']) {
            callMethod($cmd_rec['LINKED_OBJECT'].'.'.$cmd_rec['LINKED_METHOD'], $item['FULL_DATA']);
        }
    }

    //echo "Event reposne from $device_id:<br/>";
    //print_r($data);
    //exit;
}

function updateSubscription($id) {
    $rec=SQLSelectOne("SELECT * FROM onvif_devices WHERE ID=".(int)$id);
}

/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }

 if ($this->mode=='discovery') {
  $this->discovery();
  $this->redirect("?");
 }

 if ($this->data_source=='onvif_devices' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_onvif_devices') {
   $this->search_onvif_devices($out);
  }
  if ($this->view_mode=='edit_onvif_devices') {
   $this->edit_onvif_devices($out, $this->id);
  }
  if ($this->view_mode=='delete_onvif_devices') {
   $this->delete_onvif_devices($this->id);
   $this->redirect("?data_source=onvif_devices");
  }
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='onvif_commands') {
  if ($this->view_mode=='' || $this->view_mode=='search_onvif_commands') {
   $this->search_onvif_commands($out);
  }
  if ($this->view_mode=='edit_onvif_commands') {
   $this->edit_onvif_commands($out, $this->id);
  }
 }
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 //$this->admin($out);
    if ($this->ajax) {
        global $op;
        global $id;
        global $response;
        if ($op=='event' && $id!='' && $response!='') {
            $this->processEventResponse($id, json_decode($response,true));
        }
        //DebMes("Onvif AJAX: ".serialize($_GET));
    }
}
/**
* onvif_devices search
*
* @access public
*/
 function search_onvif_devices(&$out) {
  require(DIR_MODULES.$this->name.'/onvif_devices_search.inc.php');
 }
/**
* onvif_devices edit/add
*
* @access public
*/
 function edit_onvif_devices(&$out, $id) {
  require(DIR_MODULES.$this->name.'/onvif_devices_edit.inc.php');
 }
/**
* onvif_devices delete record
*
* @access public
*/
 function delete_onvif_devices($id) {
  $rec=SQLSelectOne("SELECT * FROM onvif_devices WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM onvif_commands WHERE DEVICE_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM onvif_streams WHERE DEVICE_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM onvif_devices WHERE ID='".$rec['ID']."'");
 }
/**
* onvif_commands search
*
* @access public
*/
 function search_onvif_commands(&$out) {
  require(DIR_MODULES.$this->name.'/onvif_commands_search.inc.php');
 }
/**
* onvif_commands edit/add
*
* @access public
*/
 function edit_onvif_commands(&$out, $id) {
  require(DIR_MODULES.$this->name.'/onvif_commands_edit.inc.php');
 }
 function propertySetHandle($object, $property, $value) {
   $table='onvif_commands';
   $properties=SQLSelect("SELECT ID FROM $table WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
   $total=count($properties);
   if ($total) {
    for($i=0;$i<$total;$i++) {
     //to-do
    }
   }
 }
 function processCycle() {

     include_once(DIR_MODULES.'onvif/class.ponvif.php');
     $updateTimeout=45; // subscription update timer

     $devices=SQLSelect("SELECT ID, TITLE, SUBSCRIBE, SUBSCRIPTION_TIMEOUT, SUBSCRIPTION_ADDRESS FROM onvif_devices");
     $total = count($devices);

     if (!is_array($this->onvif_devices)) {
         $this->onvif_devices=array();
     }

     $processed=array();
     for ($i = 0; $i < $total; $i++) {
         if (!$devices[$i]['SUBSCRIBE']) {
             continue;
         }
         $processed[$devices[$i]['ID']]=1;
         if (!isset($this->onvif_devices[$devices[$i]['ID']]) ) {
             DebMes("ONVIF Device ".$devices[$i]['TITLE']." adding device to cycle.");
             $this->onvif_devices[$devices[$i]['ID']]->updated=0;
             $this->onvif_devices[$devices[$i]['ID']]->polled=0;
             $this->onvif_devices[$devices[$i]['ID']]->onvif=new Ponvif();
         }
         if ((time()-$this->onvif_devices[$devices[$i]['ID']]->updated)>=$updateTimeout) {
             //DebMes("ONVIF Device ".$devices[$i]['TITLE']." updating subscription");
             $this->onvif_devices[$devices[$i]['ID']]->updated=time();
             $this->updateDevice($devices[$i]['ID'], $this->onvif_devices[$devices[$i]['ID']]->onvif,1); // quick update
             $devices[$i]=SQLSelectOne("SELECT ID, TITLE, SUBSCRIBE, SUBSCRIPTION_ADDRESS FROM onvif_devices");
         }
         if ($devices[$i]['SUBSCRIPTION_ADDRESS']!='') {
             if (!$devices[$i]['SUBSCRIPTION_TIMEOUT']) {
                 $devices[$i]['SUBSCRIPTION_TIMEOUT']=5; // default polling every 5 seconds
             }
             if ((time()-$this->onvif_devices[$devices[$i]['ID']]->polled)>=$devices[$i]['SUBSCRIPTION_TIMEOUT']) {
                 //DebMes("ONVIF Device ".$devices[$i]['TITLE']." polling events");
                 $this->onvif_devices[$devices[$i]['ID']]->polled=time();
                 $response=$this->onvif_devices[$devices[$i]['ID']]->onvif->events_Pull($devices[$i]['SUBSCRIPTION_ADDRESS']);
                 if (is_array($response)) {
                     $url=BASE_URL.'/ajax/onvif.html';
                     $post = array('id' => $devices[$i]['ID'],'op' => 'event','response'   => json_encode($response));
                     $ch = curl_init();
                     curl_setopt($ch, CURLOPT_URL, $url);
                     curl_setopt($ch, CURLOPT_POST, 1);
                     curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                     curl_setopt($ch, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:32.0) Gecko/20100101 Firefox/32.0');
                     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                     curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                     curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                     $result = curl_exec($ch);
                     curl_close($ch);

                 }
                 //DebMes("ONVIF Device ".$devices[$i]['TITLE']." events poll response:\n".json_encode($response));
             }
         }
     }

     foreach($this->onvif_devices as $k=>$v) {
         if (!$processed[$k]) {
             DebMes("ONVIF Device ".$k." removing device from cycle");
             unset($this->onvif_devices[$k]);
         }
     }

 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
  setGlobal('cycle_onvifControl', 'restart');
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS onvif_devices');
  SQLExec('DROP TABLE IF EXISTS onvif_commands');
  SQLExec('DROP TABLE IF EXISTS onvif_streams');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall($data='') {
/*
onvif_devices - 
onvif_commands - 
*/
  $data = <<<EOD
 onvif_devices: ID int(10) unsigned NOT NULL auto_increment
 onvif_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 onvif_devices: IP varchar(255) NOT NULL DEFAULT ''
 onvif_devices: USERNAME varchar(255) NOT NULL DEFAULT ''
 onvif_devices: PASSWORD varchar(255) NOT NULL DEFAULT ''
 onvif_devices: ENDPOINT_ADDRESS varchar(255) NOT NULL DEFAULT ''
 onvif_devices: TYPES text
 onvif_devices: SCOPES text
 onvif_devices: XADDRS text
 onvif_devices: SUBSCRIBE int(3) unsigned NOT NULL DEFAULT '0' 
 onvif_devices: SUBSCRIPTION_ADDRESS varchar(255) NOT NULL DEFAULT ''
 onvif_devices: SUBSCRIPTION_TIMEOUT int(10) unsigned NOT NULL DEFAULT '0' 
 onvif_devices: LATEST_POLL datetime  

 onvif_commands: ID int(10) unsigned NOT NULL auto_increment
 onvif_commands: TITLE varchar(100) NOT NULL DEFAULT ''
 onvif_commands: VALUE varchar(255) NOT NULL DEFAULT ''
 onvif_commands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 onvif_commands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 onvif_commands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 onvif_commands: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 onvif_commands: UPDATED datetime
 
 onvif_streams: ID int(10) unsigned NOT NULL auto_increment
 onvif_streams: TITLE varchar(100) NOT NULL DEFAULT ''
 onvif_streams: URL varchar(255) NOT NULL DEFAULT ''
 onvif_streams: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRmViIDI0LCAyMDE3IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
