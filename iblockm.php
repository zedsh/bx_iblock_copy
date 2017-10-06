<?
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
   
    $APPLICATION->SetTitle("");
    $APPLICATION->RestartBuffer();    
    
    CModule::IncludeModule("iblock");
    
    function get_iblocks()
    {
        $res = CIBlock::GetList(Array(),Array(),true);
        while($ar_res = $res->Fetch())
            $arRes[$ar_res['ID']]=$ar_res;
        return $arRes;
    }
    
    
    function get_iblock_types()
    {
        $ret = array();
        $db_iblock_type = CIBlockType::GetList();
        
        while($ar_iblock_type = $db_iblock_type->Fetch()){
            if($arIBType = CIBlockType::GetByIDLang($ar_iblock_type["ID"], LANG))
                $ret[] = array(
                    'ID' => $ar_iblock_type["ID"], 
                    'NAME' => $arIBType["NAME"]
                    );
        }
        return $ret;
    }
    
    function clean_array(&$arr)
    {
        foreach($arr as $k => $v){
            if(is_array($v))
                clean_array($arr[$k]);
            if(!is_array($v))
                $arr[$k] = trim($v);
            if($k{0} == '~') 
                unset($arr[$k]);
        }
    }

    
    function get_iblock_properties($IBLOCK_ID)
    {
        $properties_list = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("IBLOCK_ID"=>$IBLOCK_ID));
        $properties = array();

        $l_properties = array();

        while($prop_fields = $properties_list->Fetch()){
            if($prop_fields["PROPERTY_TYPE"] == "L"){
                $l_properties[$prop_fields['CODE']] = array();
                $property_enums = CIBlockPropertyEnum::GetList(Array("DEF"=>"DESC", "SORT"=>"ASC"), Array("IBLOCK_ID"=>$IBLOCK_ID, "CODE"=>$prop_fields["CODE"]));
                while($enum_fields = $property_enums->Fetch()){
                    $l_properties[$prop_fields['CODE']][] = $enum_fields["ID"];
                    $prop_fields["VALUES"][] = $enum_fields;
                    /*Array(
                      "VALUE" => $enum_fields["VALUE"],
                      "DEF" => $enum_fields["DEF"],
                      "SORT" => $enum_fields["SORT"]
                    );*/
                }
            }
            
            //clean_array($prop_fields);
            $properties[] = $prop_fields;
        }

    
        return array('properties' => $properties,'list_properties_map' => $l_properties);
    }
    
    function get_iblock_structure($IBLOCK_ID)
    {
        $arFields = CIBlock::GetArrayByID($IBLOCK_ID);
        $arFields["GROUP_ID"] = CIBlock::GetGroupPermissions($IBLOCK_ID);
        $props = get_iblock_properties($IBLOCK_ID); 
        return array('arFields' => $arFields, 'properties' => $props['properties']);
    }
    
    
    function change_iblock($MAKE_IBLOCK_RET)
    {
        unset($IBLOCK_DATA["ID"]);
        
        $change = array();
        if($MAKE_IBLOCK_RET['IBLOCK_DATA_CHANGED']){
            foreach($MAKE_IBLOCK_RET['IBLOCK_DATA_CHANGED'] as $prop){
                $MAKE_IBLOCK_RET['NEW_IBLOCK_DATA']['FIELDS'][$prop]['IS_REQUIRED'] = 'Y';
            }
            $ib = new CIBlock;
            $res = $ib->Update($MAKE_IBLOCK_RET["IBLOCK_ID"], $IBLOCK_DATA);
            if(!$res){
                echo "ERROR WITH CHANGE IBLOCK:".$ib->LAST_ERROR;
                var_dump($MAKE_IBLOCK_RET);
            }
        }
        
        
        if($MAKE_IBLOCK_RET['IBLOCK_PROPS_CHANGED']){
            $props_change = array();
            foreach($MAKE_IBLOCK_RET['IBLOCK_PROPS_CHANGED'] as $prop)     
               $props_change[$prop] = array('IS_REQUIRED' => 'Y');
            
            CIBlock::setFields($MAKE_IBLOCK_RET['IBLOCK_ID'], $props_change);
        }
        


    }

    
    function make_iblock($IBLOCK_DATA,$PROPERTIES,$REPLACE,$MAP_IBLOCKS)
    {
        $ib = new CIBlock;
        
        
        unset($IBLOCK_DATA["ID"]);
        
        $IBLOCK_DATA = $REPLACE + $IBLOCK_DATA; 

        $iblock_data_changed = array();

        foreach($IBLOCK_DATA['FIELDS'] as $key => $data){
            if($data['IS_REQUIRED'] == 'Y'){
                $IBLOCK_DATA['FIELDS'][$key]['IS_REQUIRED'] = 'N';
                $iblock_data_changed[] = $key;
            }
        }

        $NEW_ID = $ib->Add($IBLOCK_DATA);
        
        if($NEW_ID <= 0) 
            return array('error'=>$ib->LAST_ERROR);
        
        $ibp = new CIBlockProperty;
        
        $iblock_props_changed = array();

        foreach($PROPERTIES as $prop_fields){
            unset($prop_fields["ID"]);
            $change = false;

            if($prop_fields['IS_REQUIRED'] == 'Y'){
                $prop_fields['IS_REQUIRED'] = 'N';
                $change = true;    
            }
            if($prop_fields['PROPERTY_TYPE'] == 'E'){
                if(!isset($MAP_IBLOCKS[$prop_fields["LINK_IBLOCK_ID"]])){
                return array('error' => 'NOT SET MAP FROM '.$prop_fields["LINK_IBLOCK_ID"]);
                }
                $prop_fields["LINK_IBLOCK_ID"] = $MAP_IBLOCKS[$prop_fields["LINK_IBLOCK_ID"]];
            }
            $prop_fields["IBLOCK_ID"] = $NEW_ID;
            $prop_id = $ibp->Add($prop_fields);
            if($prop_id <= 0){
                return array('error'=>$ibp->LAST_ERROR);
            }
            if($change){
                $iblock_props_changed[] = $prop_id;
            }
        }
        
        return array('IBLOCK_ID' => $NEW_ID,'IBLOCK_DATA_CHANGED' => $iblock_data_changed,'IBLOCK_PROPS_CHANGED' => $iblock_props_changed,'NEW_IBLOCK_DATA' => $IBLOCK_DATA);
    }

    
   function make_section($SECTION_DATA,$REPLACE=array())
   {
        unset($SECTION_DATA['ID']);
        $SECTION_DATA = $REPLACE + $SECTION_DATA;
        //$SECTION_DATA['IBLOCK_ID'] = $IBLOCK_ID;
        $new_section = new CIBlockSection;  
        $result = $new_section->Add($SECTION_DATA);
        if($result <= 0)
            return array('error'=>$new_section->LAST_ERROR);
        return $result;
   }

   function make_element($ELEMENT_DATA,$REPLACE=array())
   {
        unset($ELEMENT_DATA['ID']);
        $ELEMENT_DATA = $REPLACE + $ELEMENT_DATA;
        //$SECTION_DATA['IBLOCK_ID'] = $IBLOCK_ID;
        $new_element = new CIBlockElement;  
        $result = $new_element->Add($ELEMENT_DATA);
        if($result <= 0)
            return array('error'=>$new_element->LAST_ERROR);
        return $result;
   }


   function get_all_iblocks()
   {
        $iblocks = get_iblocks();
        $structures = array();
        $map = array();
        
        foreach($iblocks as $id => $iblock){
            $map[$id] = array('LINKS' => array());
            $structures[$id] = get_iblock_structure($id);
            foreach($structures[$id]['properties'] as $prop){
               if($prop['LINK_IBLOCK_ID'] !=0) 
                   $map[$id]['LINKS'][] = $prop['LINK_IBLOCK_ID'];
            }
        }

        return compact('iblocks','structures','map');
   }

   
   function set_iblock_user_type_entity($ENTITY_DATA,$REPLACE)
   {
        unset($ENTITY_DATA['ID']);
        $ENTITY_DATA = $REPLACE + $ENTITY_DATA;
        $e = new CUserTypeEntity();
        $result = $e->Add($ENTITY_DATA);
            if($result <=0 ){
                to_log('ERROR WITH ADD ENTITY!');
                to_log($ENTITY_DATA);
                exit;
            }
        return true;
   
   }
   
   function get_lang_user_type(&$user_type)
   {
        $names= array(
        'EDIT_FORM_LABEL',
        'LIST_COLUMN_LABEL',
        'LIST_FILTER_LABEL',
        'ERROR_MESSAGE',
        'HELP_MESSAGE',
        );
        
        $langs = array('ru','en');

        $ret = array();

        foreach($names as $name){
            foreach($langs as $lang){
                if(!isset($ret[$name]))
                    $ret[$name] = array();
                $ret[$name][$lang] = $user_type[$name];
            }
            unset($user_type[$name]);
        }
   
        return $ret;
   }
   
   function get_iblock_user_type_entity($IBLOCK_ID)
   {
        $ret = array();
        $ret['IBLOCK'] = array();
        $ret['SECTION'] = array();


        $db_user_types = CUserTypeEntity::GetList(false,array('ENTITY_ID' => "IBLOCK_".$IBLOCK_ID."_SECTION",'LANG' => 'ru'));

        while($db_user_type = $db_user_types->Fetch()){
            $add = get_lang_user_type($db_user_type);
            $db_user_type = $add + $db_user_type;
            $ret['SECTION'][] = $db_user_type;
        }
        
        $db_user_types = CUserTypeEntity::GetList(false,array('ENTITY_ID' => "IBLOCK_".$IBLOCK_ID,'LANG' => 'ru'));
        
        while($db_user_type = $db_user_types->Fetch()){
            $add = get_lang_user_type($db_user_type);
            $db_user_type = $add + $db_user_type;
            $ret['IBLOCK'][] = $db_user_type;
        }

        return $ret;
   }

   function get_sections($IBLOCK_ID)
   {
        $sections = array();
        $arFilter = Array('IBLOCK_ID'=>$IBLOCK_ID);
        $db_list = CIBlockSection::GetList(Array('ID'=>'asc'), $arFilter, false,array('*','UF_*'));
        
        while($ar_result = $db_list->Fetch())
        {
            $sections[$ar_result['ID']] = $ar_result;
        }
   
        //clean_array($sections);
        return $sections;
   }
   
   function get_elements($IBLOCK_ID)
   {
        $elements = array();
        $arFilter = Array('IBLOCK_ID'=>$IBLOCK_ID);
        $db_list = CIBlockElement::GetList(Array('ID'=>'asc'), $arFilter, false);
        
        while($ar_result = $db_list->Fetch())
        {
            $props = array();
            $props_db = CIBlockElement::GetProperty($IBLOCK_ID,$ar_result['ID']);
            
            while($prop = $props_db->Fetch()){
                //clean_array($prop);
                if ($prop['PROPERTY_TYPE']=='L'){
                  if ($prop['MULTIPLE']=='Y'){
                      if(!isset($props[$prop['CODE']])){
                        $props[$prop['CODE']] = array();
                      }
                      $props[$prop['CODE']][] = $prop['VALUE'];
                } else {
                        $props[$prop['CODE']] = $prop['VALUE'];
                }
                continue;
             }
               
                if($prop['PROPERTY_TYPE'] == 'F'){
                    if($prop['MULTIPLE']=='Y') {
                        if (is_array($prop['VALUE'])){
                            foreach ($prop['VALUE'] as $key => $arElEnum) 
                            $props[$property['CODE']][$key]=CFile::CopyFile($arElEnum);                             
                        }                 
                    }else 
                        $props[$prop['CODE']] = CFile::CopyFile($prop['VALUE']);
                continue;
                }
                
                if ($prop['MULTIPLE']=='Y'){
                      if(!isset($props[$prop['CODE']])){
                        $props[$prop['CODE']] = array();
                      }
                      $props[$prop['CODE']][] = $prop['VALUE'];
                      continue;
                } 
                
                $props[$prop['CODE']] = $prop['VALUE'];
            
            }
            
            $elements[$ar_result['ID']] = $ar_result;
            $elements[$ar_result['ID']]['PROPERTY_VALUES'] = $props;
        }
       

       // clean_array($elements);
        return $elements;
   }

   function process_file_field($field_value)
   {
       if(!$field_value) 
            return $field_value;
       $ret = CFile::CopyFile($field_value);
       if(!$ret){
           to_log("ERROR WITH COPY FILE $field_value!");
           return null;
           //exit;
       }
       //return CFile::GetFileArray($ret);
       return CFile::MakeFileArray($ret);
   }

   function to_log($data)
   {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
   
   }
  
   function map_list_properties(&$elements,$IBLOCK_ID,$NEW_IBLOCK_ID)
   {
        $src = get_iblock_properties($IBLOCK_ID);
        $dst = get_iblock_properties($NEW_IBLOCK_ID);

        $map = array();

        foreach($src['list_properties_map'] as $code => $ids){
            foreach($ids as $number => $id){
                if(!isset($dst['list_properties_map'][$code][$number])){
                    to_log("LIST MAP UNFORTUNALITY");
                    to_log($src);
                    to_log($dst);
                    exit;
                }
                if(!isset($map[$code])) $map[$code] = array();
                $map[$code][$id] = $dst['list_properties_map'][$code][$number];
            }
        }
        
        foreach($elements as $id => $element){
            foreach($map as $code => $links){
                if(isset($element['PROPERTY_VALUES'][$code])){
                    if(is_array($element['PROPERTY_VALUES'][$code])){
                        foreach($element['PROPERTY_VALUES'][$code] as $key => $src_id){
                            if(!$src_id)
                                continue;
                            if(!isset($links[$src_id])){
                                to_log('NOT ID IN MAP');
                                to_log($map);
                                to_log($src_id);
                                exit;
                            } 
                            $elements[$id]['PROPERTY_VALUES'][$code][$key] = $links[$src_id];
                        }
                    }else{
                        if($element['PROPERTY_VALUES'][$code]){
                            $src_id = $element['PROPERTY_VALUES'][$code];
                            if(!isset($links[$src_id])){
                                to_log('NOT ID IN MAP');
                                to_log($map);
                                to_log($src_id);
                                exit;
                            } 

                            $elements[$id]['PROPERTY_VALUES'][$code] = $links[$src_id];
                        }
                    }
                }
            
            }
        }
        return true;
   }

   function copy_iblock($all_iblocks,$IBLOCK_ID,$REPLACE,$MAP_IBLOCKS)
   {
       
       $data = make_iblock(
            $all_iblocks['structures'][$IBLOCK_ID]['arFields'],
            $all_iblocks['structures'][$IBLOCK_ID]['properties'],
            $REPLACE,
            $MAP_IBLOCKS
            );
       if(isset($data['error'])){
            to_log("Error with make_iblock:".$data['error']);
            exit;
       }
        
       
       $NEW_IBLOCK_ID = $data['IBLOCK_ID'];
       
       $user_types = get_iblock_user_type_entity($IBLOCK_ID);

            foreach($user_types['IBLOCK'] as $utype){
                set_iblock_user_type_entity($utype,array("ENTITY_ID" => "IBLOCK_".$NEW_IBLOCK_ID));
            }

            foreach($user_types['SECTION'] as $utype){
                set_iblock_user_type_entity($utype,array("ENTITY_ID" => "IBLOCK_".$NEW_IBLOCK_ID."_SECTION"));
            }



       $sections = get_sections($IBLOCK_ID);
       $sections_new_ids = array();
       foreach($sections as $id => $section){
            if($section['IBLOCK_SECTION_ID'] != ''){
                if(!isset($sections_new_ids[$section['IBLOCK_SECTION_ID']])){
                    to_log("Error with section import, parent section not set");
                    to_log($section);
                    exit;
                }
                $section['IBLOCK_SECTION_ID'] = $sections_new_ids[$section['IBLOCK_SECTION_ID']];
            }
            
                
            $section['PREVIEW_PICTURE'] = process_file_field($section['PREVIEW_PICTURE']);
            $section['PICTURE'] = process_file_field($section['PICTURE']);
            
            $result_section = make_section($section,array(
                'IBLOCK_ID' => $NEW_IBLOCK_ID,
            ));

            if(isset($result_section['error'])){
                to_log("ERROR with section add:".$result_section['error']);
                to_log($section);
                exit;
            }
            $sections_new_ids[$id] = $result_section;

       }
       
       $elements = get_elements($IBLOCK_ID);
       
       map_list_properties($elements,$IBLOCK_ID,$NEW_IBLOCK_ID);

       foreach($elements as $element){
            if($element['IBLOCK_SECTION_ID'] != ''){
                if(!isset($sections_new_ids[$element['IBLOCK_SECTION_ID']])){
                    to_log("Error with item import, parent section not set");
                    to_log($element);
                    exit;
                }
            $element['IBLOCK_SECTION_ID'] = $sections_new_ids[$element['IBLOCK_SECTION_ID']];
            }
            $element['DETAIL_PICTURE'] = process_file_field($element['DETAIL_PICTURE']);
            $element['PREVIEW_PICTURE'] = process_file_field($element['PREVIEW_PICTURE']);

            $result_element = make_element($element,array(
                'IBLOCK_ID' => $NEW_IBLOCK_ID,
            ));

            if(isset($result_element['error'])){
                to_log("ERROR with element add:".$result_element['error']);
                to_log($element);
                exit;
            }
       }
       
       change_iblock($data);

       return array('IBLOCK_ID' => $NEW_IBLOCK_ID);
   }
   /*

    iblock
        array('IBLOCK_TYPE_ID' => "",)
    section
        array(
            'IBLOCK_ID' => '',
            'IBLOCK_SECTION_ID' => '',
            'IBLOCK_TYPE_ID' => '',
            'IBLOCK_CODE' => ''
            )
    element
    file

   */
   
   function get_releated_blocks($all_iblocks,$target_iblocks)
   {
        $releated = array();
        foreach($target_iblocks as $iblock){
            if(count($all_iblocks['map'][$iblock]['LINKS']) > 0){
                foreach($all_iblocks['map'][$iblock]['LINKS'] as $link){
                    if(!in_array($link,$releated)) $releated[]=$link;
                }
            }
        }
   
        return $releated;
   }
   
   function main()
   {
       $iblocks = array(
       37,
       36,
       41,
       27,
       61,
       25,
       28,
       29,
       30,
       32,
       33,
       34,
       59,
       52,
       56,
       55,
       31,
       40,
       58,
       53,
       57,
       60,
       51,
       54,
       45,
       39,
       24,
       23,
       3,
       15,
       16,
       13,
       10,
       22,
       21,
       12,
       17,
       1,
       9,
       14,
       11,
       2,
       );
      
      //$iblocks = array(54);
      //to_log((array)get_iblock_user_type_entity(54));
      //exit;
      // будем высчитывать релеации вручную пока

      // $all_iblocks = get_all_iblocks();
      // 
      // $releated = array();
      // $releated[] = get_releated_blocks($all_iblocks,$iblocks);
      // $releated[] = get_releated_blocks($all_iblocks,$releated[0]);
      // $releated[] = get_releated_blocks($all_iblocks,$releated[1]);
      // $releated[] = get_releated_blocks($all_iblocks,$releated[2]);
      // to_log($releated);
      // to_log($all_iblocks['map']);
      // exit;

       
       $all_iblocks = get_all_iblocks();
       
       $new_iblocks = array();

       foreach($iblocks as $iblock){
            $REPLACE = array('IBLOCK_TYPE_ID' => 'en','LID' => 'en');

            $ret = copy_iblock($all_iblocks,$iblock,$REPLACE,$new_iblocks);
            $new_iblocks[$iblock] = $ret['IBLOCK_ID'];
       }

        to_log("NEW_IBLOCKS:");
        to_log($new_iblocks);
   }
   
   

   main();
   
