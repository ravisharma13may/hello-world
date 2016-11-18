<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 * $caller ->1 Admin; 2-> Advertiser
 */
//if(isset($_REQUEST['action_bannerstate']) && $_REQUEST['action_bannerstate']==1){
//    $adStateUpdated = updateAdState();
//    
//        if ( $_REQUEST['returnurl'] && $adStateUpdated)
//        header("Location: ".$_REQUEST['returnurl']."?clientid=".$clientid."&campaignid=".$campaignid."&bannerid=".$bannerid);
//        else if($adStateUpdated)
//        header("Location: campaign-banners.php?clientid=".$clientid."&campaignid=".$campaignid);
//    
//}   
function updateAdState($bannerid,$clientid,$campaignid,$value){
    
    

    
    $unsetBanner = array();
    if (!empty($bannerid))
    {


        $doBanners = OA_Dal::factoryDO('banners');
        $doBanners->get($bannerid);
        $bannerName = $doBanners->description;
        $compiledlimitation = $doBanners->compiledlimitation;
        $show_limitation = $doBanners->show_limitation;


        $doBanners->ad_direct_status = $value;
        $doBanners->updated = date('Y-m-d H:i:s');
        $doBanners->update();

        /*NIKHIL GET THE BANNER synced CHILDERN where we Need update based on the*/
        OA_DB::singleton();
        $childern_query = "update ox_banners b inner join ox_banners_data bd on bd.bannerid = b.bannerid set b.ad_direct_status = {$value} where bd.parentid = {$bannerid} and bd.sync_type in ('1','2');";
	//$query = "SELECT clientid from ox_campaigns WHERE campaignid=".$aBanner['campaignid'];
        $childern_resultset = mysql_query($childern_query);
        $childern_count  = mysql_affected_rows();
        
        $aBanner = $doBanners->toArray();
        AdStateManagerMail($aBanner,$PostValue=NULL,1);

        $translation = new OX_Translation();
        switch($value){
            case '0':
                $message = "The Ad has been Approved";
            break;
            case '2':
                $message = "The Ad has been Rejected";
            break;

        }

        $translated_message = $translation->translate($message, array (
        MAX::constructURL(MAX_URL_ADMIN, "banner-edit.php?clientid=$clientid&campaignid=$campaignid&bannerid=$bannerid"),
        htmlspecialchars($bannerName)
        ));
        OA_Admin_UI::queueMessage($translated_message, 'local', 'confirm', 0);
    }

    return true;
}
function getAdState($PostValue,$aBanner,$insert){ 
    //echo '<pre>'; print_r($PostValue);  echo '>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>';
    //print_r($aBanner);exit;
    $returnFlag = 0;
    //IF ACCOUNT MANAGER INSERT OR UPDATE THE ADS, IT WILL BE IN APPROVED STATE
    if(OA_Permission::isMarket() && OA_Permission::isAccount(OA_ACCOUNT_MANAGER)){
         $returnFlag = 0;
    }
    //IF AD IS INSERTED BY THE ADVERTISER
    else
    if(OA_Permission::isMarket() && OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER) && $insert){
        $returnFlag =  1;
    }
    else{
        //IF AD IS UPDATED BY THE ADVERTISER
        if($PostValue['main_app_ad_type']=='image')  {
	
	    if($PostValue['type']=='web'){
                $Sizes = array_reverse($GLOBALS['_MAX']['CONF']['bannerSizes']);
                $isBannerImageLocation  = 'image_location_banner';
            }else{
                $Sizes = array_reverse($GLOBALS['_MAX']['CONF']['interstitialBannerSizes']); 
                $isBannerImageLocation  = 'image_location';
            }
            
            if($PostValue[$isBannerImageLocation]== 'local'){
            foreach($Sizes as $key=>$value){
                    if(isset($PostValue['final_upload_'.$key]) && $PostValue['final_upload_'.$key]!='') {
                        $returnFlag =  1;
                    }
                }
            }else if($PostValue[$isBannerImageLocation]== 'external') {
                $external_imageurl = json_decode($aBanner['external_images'],true);
                if(sizeof($external_imageurl)>0){
                foreach($external_imageurl as $key=>$value)
                $external_image_check['external_'.$key] = $value;  
                }
		if(empty($external_image_check)){
		    $returnFlag =  1;
		}else 
                {
                    foreach($Sizes as $key=>$value){
                        if(isset($PostValue['external_'.$key]) && $PostValue['external_'.$key]!='' && isset($external_image_check['external_'.$key]) && $PostValue['external_'.$key]!= $external_image_check['external_'.$key]) 
                        {
                        $returnFlag =  1;
                        }
		    }
		}
            }
        }
        //IF AD IS RICH MEDIA AND THERE IS NEW HTML FILE IS UPLOADED
        //if(isset($PostValue['main_app_ad_type']) && $PostValue['main_app_ad_type']=='html' && isset($_FILES['upload_html']['type']) && $_FILES['upload_html']['type']!="") {
        //    $returnFlag =  1;
        //}
        //IF AD TYPE IS VAST
        if(isset($PostValue['main_app_ad_type']) && $PostValue['main_app_ad_type']=='vast') {
            $bannervast_data        = xml2array($aBanner['bannervast']);
            $vast_xml_type          = ($aBanner['vast_type']=='inline') ? "InLine" : "Wrapper";
            $vast_xml_db['ad_title']= $bannervast_data['VAST']['Ad'][$vast_xml_type]['AdTitle'];
            //TO CHECK TEXT IS CHANGED 
            if(isset($PostValue['vast_ad_title']) &&  $PostValue['vast_ad_title']!= $vast_xml_db['ad_title'])
            {
                $returnFlag = 1;
            }
            //TO CHECK VIDEO IS CHANGED 
            if(isset($aBanner['vast_type'])&& $aBanner['vast_type']=='inline' && isset($_FILES['upload_vast']['type']) && $_FILES['upload_vast']['type']!=""){
                $returnFlag =  1;
            }
            //TO CHECK XML LINK IS CHANGED 
            $vast_xml_db['xml_url'] = $bannervast_data['VAST']['Ad'][$vast_xml_type]['VASTAdTagURI'];
            if(isset($aBanner['vast_type'])&& $aBanner['vast_type']=='wrapper' && isset($PostValue['vast_xml_url']) && $PostValue['vast_xml_url']!=$vast_xml_db['xml_url']){
                $returnFlag =  1;
            }
        }
        //IF AD IS DYNAMIC BANNER
        if(isset($PostValue['main_app_ad_type']) && $PostValue['main_app_ad_type']=='hybrid' && isset($PostValue['richmedia_type']) && $PostValue['richmedia_type']=='dynamic_banner'){
            if(isset($aBanner['da_title_text']) && $aBanner['da_title_text']!= $PostValue['dynamic_banner_mob_tag_text']
                   || isset($aBanner['da_price']) && $aBanner['da_price']!= $PostValue['dynamic_banner_mob_price']
                   || isset($aBanner['da_install_count']) && $aBanner['da_install_count']!= $PostValue['dynamic_banner_mob_installs']
                   || isset($aBanner['da_sample_review']) && $aBanner['da_sample_review']!= $PostValue['dynamic_banner_mob_user_testimonial']
                   || isset($aBanner['da_det_text']) && $aBanner['da_det_text']!= $PostValue['dynamic_banner_mob_det_text']
                   || isset($PostValue['final_dynamic_banner_mob_icon_image']) && $PostValue['final_dynamic_banner_mob_icon_image']!="retain"
                   || isset($PostValue['final_dynamic_banner_mob_large_image']) && $PostValue['final_dynamic_banner_mob_large_image']!="retain" ){
                $returnFlag =  1;
            }
        }
        //VSERV UPDATE:20150810 NIKHIL APP INSTALL Native Ads 
        
//            echo '<pre>'; print_r($PostValue);  echo '>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>';
//    print_r($aBanner);
//    exit;
       if(isset($PostValue['main_app_ad_type']) && $PostValue['main_app_ad_type']=='hybrid' && isset($PostValue['richmedia_type']) && $PostValue['richmedia_type']=='dynamic_billboard' && $aBanner['media_type'] =='psd'){
            if(isset($aBanner['da_title_text']) && $aBanner['da_title_text']!= $PostValue['dynamic_billboard_oad_title_text']
                   || isset($aBanner['da_playstore_url']) && $aBanner['da_playstore_url']!= $PostValue['dynamic_billboard_psd_store_url']
                   || isset($aBanner['da_sample_review']) && $aBanner['da_sample_review']!= $PostValue['dynamic_billboard_oad_sample_review']
                   || isset($aBanner['da_det_text']) && $aBanner['da_det_text']!= $PostValue['dynamic_billboard_oad_det_text']

                   || isset($PostValue['final_dynamic_billboard_oad_span_image']) && $PostValue['final_dynamic_billboard_oad_span_image']!="retain"
                   || isset($PostValue['final_dynamic_billboard_oad_ss_image']) && $PostValue['final_dynamic_billboard_oad_ss_image']!="retain"
                   || isset($PostValue['final_dynamic_billboard_oad_large_image']) && $PostValue['final_dynamic_billboard_oad_large_image']!="retain" ){
                $returnFlag =  1;
            }
        }
                //VSERV UPDATE ENDS
        //IF AD IS DYNAMIC BILLBOARD 
        if(isset($PostValue['main_app_ad_type']) && $PostValue['main_app_ad_type']=='hybrid' && isset($PostValue['richmedia_type']) && $PostValue['richmedia_type']=='dynamic_billboard'){
            if(isset($aBanner['da_title_text']) && $aBanner['da_title_text']!= $PostValue['dynamic_billboard_mob_tag_text']
                   || isset($aBanner['da_price']) && $aBanner['da_price']!= $PostValue['dynamic_billboard_mob_price']
                   || isset($aBanner['da_install_count']) && $aBanner['da_install_count']!= $PostValue['dynamic_billboard_mob_installs']
                   || isset($aBanner['da_sample_review']) && $aBanner['da_sample_review']!= $PostValue['dynamic_billboard_mob_user_testimonial']
                   || isset($aBanner['da_det_text']) && $aBanner['da_det_text']!= $PostValue['dynamic_billboard_mob_det_text']
                   || isset($PostValue['final_dynamic_billboard_mob_icon_image']) && $PostValue['final_dynamic_billboard_mob_icon_image']!="retain"
                   || isset($PostValue['final_dynamic_billboard_mob_span_image']) && $PostValue['final_dynamic_billboard_mob_span_image']!="retain"
                   || isset($PostValue['final_dynamic_billboard_mob_ss_image']) && $PostValue['final_dynamic_billboard_mob_ss_image']!="retain"
                   || isset($PostValue['final_dynamic_billboard_mob_large_banner']) && $PostValue['final_dynamic_billboard_mob_large_banner']!="retain"
                   || isset($PostValue['final_dynamic_billboard_mob_large_image']) && $PostValue['final_dynamic_billboard_mob_large_image']!="retain" ){
                $returnFlag =  1;
            }
        }
        //IF AD IS SITE BILLBOARD
        if($PostValue['main_app_ad_type'] == 'html' && $PostValue['richmedia_type'] == 'direct_video'){
            if($PostValue['final_upload_direct_video_poster']!='' || $PostValue['final_upload_direct_video']!='' 
                    || $PostValue['direct_video_button_label']!= $aBanner['button_label'] ){
                $returnFlag = 1;
            }
        }
        //IF AD IS ALERT AD
        if($PostValue['main_app_ad_type'] == 'html' && $PostValue['richmedia_type'] == 'alert_banner'){
            if($PostValue['alert_title']!= $aBanner['alert_title'] || $PostValue['alert_text']!=$aBanner['bannertext']){
                $returnFlag = 1;
            }
            $Sizes = array_reverse($GLOBALS['_MAX']['CONF']['bannerSizes']);
            foreach($Sizes as $key=>$value)
            {
                  if(isset($PostValue['final_upload_alert_banner_'.$key]) && $PostValue['final_upload_alert_banner_'.$key]!='') 
                  {
                       $returnFlag = 1;
                  }
            }
        }
    }
    //IF AD STATE IS ALREADY IS PENDING APROVAL AND REJECTED STATE
    if($returnFlag!=1){
        $returnFlag = FALSE;
    }
    return $returnFlag;
}
function AdStateManagerMail($aBanner,$PostValue,$caller=2,$insertFlag=0)
{
    OA_DB::singleton();
    if($caller==1){
        
       $query = "SELECT cl.email, cl.clientname,c.campaignname FROM ox_clients as cl INNER JOIN ox_campaigns as c ON (cl.clientid = c.clientid) WHERE c.campaignid=".$aBanner['campaignid'];
	//$query = "SELECT clientid from ox_campaigns WHERE campaignid=".$aBanner['campaignid'];
        $AdvertiserExe = mysql_query($query);
        $AdvertiserResult = mysql_fetch_assoc($AdvertiserExe);
        
       switch($aBanner['ad_direct_status']){
            case '0':
                $mailBody['subject'] = "Your Banner is Approved!";
                
                $mailBody['email_body'] = 'Dear Advertiser,<br/>
                                           Thank you for submitting the Banner ID '.$aBanner['bannerid'].'. Our team has reviewed your submission and the banner is APPROVED.<br/>
                                           Please write to us at adops@vserv.com in case you have any questions.</br>
                                           Thank You!<br/>
                                           Regards,<br/>
                                           Team Vserv';
                break;
            case '2':
                $mailBody['subject'] = "Banner Update Status";
                $mailBody['email_body'] = 'Dear Advertiser,<br/>
                                        Thank you for submitting the Banner ID '.$aBanner['bannerid'].'. Our team has reviewed your submission and the banner is REJECTED as it does not comply with our <link>creative policy</link>. For further details, please write to us at adops@vserv.com and we will be happy to help you.<br/>
                                        Thank You!<br/>
                                        Regards,<br/>
                                        Team Vserv';
                break;
        }
       $advertiserData['email'] = $AdvertiserResult['email'];
       sendAdStateMail($mailBody,$advertiserData);
       
    } else {
      	$query = "SELECT m.email,m.name,c.campaignname,c.clientid FROM ox_managers as m "
                . "INNER JOIN ox_campaigns as c ON (m.id = c.campaign_manager) WHERE c.campaignid=".$PostValue['campaignid'];
        $ManagerResult = mysql_query($query);
        $ManagerResult = mysql_fetch_assoc($ManagerResult);
        
        if($ManagerResult['email']!=""){
            $managerData['email'] = $ManagerResult['email'];
	}else{
	    $managerData['email'] = $GLOBALS['_MAX']['CONF']['adDefaultEmail']['toEmailId'];	
        }
        $subject_content = 'Updated';
        $body_content = 'update ';
        if($insertFlag==1){
            $subject_content = 'Added';
            $body_content = '';
        }
	$mailBody['subject'] = 'New Banner '.$subject_content.' – Please review.';
	$mailBody['email_body'] = 'Hello!<br/>A new banner '.$body_content.'is awaiting your review.<br/>
                                Advertiser ID: '.$ManagerResult['clientid'].'<br/>
                                Campaign ID: '.$PostValue['campaignid'].'<br/>
                                Banner ID: '.$aBanner['bannerid'].'<br/>
                                '.$PostValue['banner_url'].'<br/><br/>
                                Please review it in order to approve or reject the banner. Once you approve/reject, an email will be sent to the Advertiser informing him about the same.<br/>
                                Thank You!';
	
        sendAdStateMail($mailBody,$managerData);
    }
}

/**
 * 
 * @param type $mailBody
 * @param type $managerData
 */
function sendAdStateMail($mailBody,$managerData) 
{
    
	
    $m= new Mail; // create the mail object
    $m->From("mis@vserv.mobi");
    $m->To($managerData['email']);
    $m->Cc($GLOBALS['_MAX']['CONF']['adDefaultEmail']['ccEmailId']);
    $m->Content_type("text/html");
    $m->Subject($mailBody['subject']);
    $m->Body($mailBody['email_body']);
    //print_r($m); exit;
    #$m->Priority(4);
    $m->Send();
    $m=null;
    
}
