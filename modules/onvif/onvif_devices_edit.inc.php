<?php
/*
* @version 0.1 (wizard)
*/
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $table_name='onvif_devices';

  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

  if ($this->mode=='refresh') {
   $result=$this->updateDevice($rec['ID']);
   if (!$result) {
    echo "Failed to fetch data";exit;
   }
   $this->redirect("?view_mode=".$this->view_mode."&id=".$rec['ID']);
  }


  if ($this->mode=='update') {
   $ok=1;
  // step: default
  if ($this->tab=='') {
  //updating '<%LANG_TITLE%>' (varchar, required)
   global $title;
   $rec['TITLE']=$title;
   if ($rec['TITLE']=='') {
    $out['ERR_TITLE']=1;
    $ok=0;
   }
  //updating 'IP' (varchar)
   global $ip;
   $rec['IP']=$ip;
  //updating 'USERNAME' (varchar)
   global $username;
   $rec['USERNAME']=$username;
  //updating 'PASSWORD' (varchar)
   global $password;
   $rec['PASSWORD']=$password;

      global $subscribe;
      $rec['SUBSCRIBE']=(int)$subscribe;

      global $subscription_timeout;
      $rec['SUBSCRIPTION_TIMEOUT']=(int)$subscription_timeout;


      if (!$rec['ID']) {
          global $xaddrs;
          $rec['XADDRS']=$xaddrs;
      }

  }
  // step: data
  if ($this->tab=='data') {
  }
  //UPDATING RECORD
   if ($ok) {
    if ($rec['ID']) {
     SQLUpdate($table_name, $rec); // update
    } else {
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record
    }
    $this->updateDevice($rec['ID'],$this->onvif);
    $out['OK']=1;
   } else {
    $out['ERR']=1;
   }
  }
  // step: default
  if ($this->tab=='') {
  }
  // step: data
  if ($this->tab=='data') {
  }
  if ($this->tab=='data') {
   //dataset2
   $new_id=0;
   global $delete_id;
   if ($delete_id) {
    SQLExec("DELETE FROM onvif_commands WHERE ID='".(int)$delete_id."'");
   }
   $properties=SQLSelect("SELECT * FROM onvif_commands WHERE DEVICE_ID='".$rec['ID']."' ORDER BY ID");
   $total=count($properties);
   for($i=0;$i<$total;$i++) {
    if ($properties[$i]['ID']==$new_id) continue;
    if ($this->mode=='update') {
      /*
      global ${'title'.$properties[$i]['ID']};
      $properties[$i]['TITLE']=trim(${'title'.$properties[$i]['ID']});
      global ${'value'.$properties[$i]['ID']};
      $properties[$i]['VALUE']=trim(${'value'.$properties[$i]['ID']});
      */
      global ${'linked_object'.$properties[$i]['ID']};
      $properties[$i]['LINKED_OBJECT']=trim(${'linked_object'.$properties[$i]['ID']});
      global ${'linked_property'.$properties[$i]['ID']};
      $properties[$i]['LINKED_PROPERTY']=trim(${'linked_property'.$properties[$i]['ID']});
      global ${'linked_method'.$properties[$i]['ID']};
      $properties[$i]['LINKED_METHOD']=trim(${'linked_method'.$properties[$i]['ID']});
      SQLUpdate('onvif_commands', $properties[$i]);
      $old_linked_object=$properties[$i]['LINKED_OBJECT'];
      $old_linked_property=$properties[$i]['LINKED_PROPERTY'];
      if ($old_linked_object && $old_linked_object!=$properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property!=$properties[$i]['LINKED_PROPERTY']) {
       removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
      }
      if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
       addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
      }
     }
   }
   $out['PROPERTIES']=$properties;   
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }
  outHash($rec, $out);


  if ($rec['SCOPES']) {
   $tmp=explode(' ', $rec['SCOPES']);
   $out['SCOPES']=implode('<br/>', $tmp);
  }

  if ($rec['TYPES']) {
   $tmp=explode(' ', $rec['TYPES']);
   $out['TYPES']=implode('<br/>', $tmp);
  }

  if ($rec['XADDRS']) {
   $tmp=explode(' ', $rec['XADDRS']);
   $out['XADDRS']=implode('<br/>', $tmp);
  }

  if ($rec['ID']) {
   $streams=SQLSelect("SELECT * FROM onvif_streams WHERE DEVICE_ID=".$rec['ID']." ORDER BY TITLE");
   if ($streams[0]['ID']) {
    $out['STREAMS']=$streams;
   }
  }
