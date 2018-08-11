<?php

class PIEX
{
    var $feed;
    var $feed_item;
    var $template_data = array();
    var $snip_path;
    
    function __construct()
	{
        if (!is_file($this->snip_path.'simplepie/simplepie.inc')) die('simplepie not found');
        
        define('SIMPLEPIE_CACHE_LOCATION',$modx->config['base_path'] . 'assets/cache/');
        include_once($this->snip_path.'simplepie/simplepie.inc');
        $this->feed = new SimplePie();
    }
    
    function execute($param)
    {
        global $modx;
        
        $this->snip_path = $modx->config['base_path'].'assets/snippets/';
        
        extract($param);
        // Templateの分解
        $tpl= str_replace('<!-- start -->','\0',$tpl);
        $tpl = str_replace('<!-- end -->','\0',$tpl);
        $tpl_parts = explode('\0',$tpl,3);
        if (count($tpl_parts) != 3)
        {
        	return 'Template Parse Error';
        }
        $this->template_data['date'] = $dateformat;
        $embed_array = array();
        if ($embedparam!='')
        {
        	$lines = explode("\n",$embedparam);
            foreach ($lines as $line)
            {
                list($var,$value) = explode('=',$line);
                $value = rtrim($value);
                if ($var != '')
                {
                	switch (strtolower($value))
                    {
                        case 'true':
                            $emebed_array[$var] = true;
                            break;
                        case 'false':
                            $emebed_array[$var] = false;
                            break;
                        default:
                            $embed_array[$var] = $value;
                            break;
                    }
                }
            }
        }
        $this->template_data['embed'] = $embed_array;
        
        if (!is_array($feedURL))
        {
        	$tmpUrl = $feedURL;
            unset($feedURL);
            $feedURL[0] = $tmpUrl;
        }
        else
        {
            for ($i = 0;$i < count($feedURL);++$i)
            {
                if (preg_match('/##(.+)\|(e|s|u)##/i',$feedURL[$i],$matches))
                {
                    switch ($matches[2])
                    {
                        case 'e':                       // EUC-JP
                            $word = mb_convert_encoding($matches[1],'EUC-JP','AUTO');
                            break;
                        case 's':                       // SJIS
                            $word = mb_convert_encoding($matches[1],'SJIS','AUTO');
                            break;
                        case 'u':                       // UTF-8
                            $word = mb_convert_encoding($matches[1],'UTF-8','AUTO');
                            break;
                    }
                    
                    $word = rawurlencode($word);
                    $feedURL[$i] = preg_replace('/##(.+)##/i',$word,$feedURL[$i]);

                }
            }
        }
        if (count($feedURL) == 1)
        {
        	$tmpUrl = $feedURL[0];
            unset($feedURL);
            $feedURL = $tmpUrl;
        }
        
        $this->feed->set_feed_url($feedURL);

        if ($extclass != '')
        {   
            if (is_file($this->snip_path . "simplepie/simplepie_{$extclass}.inc"))
            {
                include_once $this->snip_path . "simplepie/simplepie_{$extclass}.inc";
                $extclass_name = 'SimplePie_Item_'.ucfirst($extclass);
                $this->feed->set_item_class($extclass_name);
            }
        }

        if ($cache_enable === true) // Cache Enable??
        {
            $this->feed->set_cache_location(SIMPLEPIE_CACHE_LOCATION);
            $this->feed->set_cache_duration($cache_time);
            $this->feed->enable_cache(true);
        }
        else
        {
        	$this->feed->enable_cache(false);
        }
        $this->feed->strip_comments(true);

        $this->feed->init();
        $this->feed->handle_content_type();
        if (isset($this->feed->error))
        {
        	$feedURLstr = implode(',',$feedURL);
            $data =preg_replace('/\[\+feed_url\+\]/im',$feedURL,$error);
            $data =preg_replace('/\[\+error\+\]/im',$this->feed->error,$data);
            return $data;
        }
        
        $pattern = '#\[\+([a-z_]+|([^\(]+)\(([^\)]+)\))\+\]#msiU';
        $output = preg_replace_callback($pattern, array(&$this,'site_parse_callback'),$tpl_parts[0]);
        $i = 0;
        $items = $this->feed->get_items();
        foreach ($items as $item)
        { 
            if (++$i <= $max)
            {
                $this->feed_item = &$item;
                $item_text = preg_replace_callback($pattern, array(&$this,'item_parse_callback'),$tpl_parts[1]);
                
                if ($rows) $mod = ($i%$rows)+1;
                else       $mod = $i;
                
                $item_text = preg_replace('#\[\+no\+\]#msiU',$mod,$item_text);
                $output .= $item_text;
            }
        }
        $footer= preg_replace_callback($pattern, array(&$this,'site_parse_callback'),$tpl_parts[2]);
        $output .= $footer;
        return $output;
    }
    
    // Feed Parser Callbak
    function site_parse_callback($match)
    {
        global  $modx;
        
        if (count($match) > 2)
        {
            $func_name = $match[2];
            $option = explode(',',$match[3]);
        }
        else
        {
            $func_name = $match[1];
            $option = false;
        }
        
        $func = "get_{$func_name}";
        
        if (preg_match('/^subscribe_/',$func_name)) $func = $func_name;
        
        if (method_exists($this->feed, $func) == true) 
        {
            switch ($func_name)
            {
                case 'image_url':
                    if ($value === '') $value = $this->feed->get_title();
                    else
                    {
                        $title = $this->feed->get_image_title();
                        
                        if ($title === false) $title = $this->feed->get_title();
                        
                        $add_opt = array();
                        if ($option != false)
                        {
                            if (isset($option[0])) $add_opt[] = 'width=' .$option[0];
                            if (isset($option[1])) $add_opt[] = 'height='.$option[1];
                        }
                        $value = sprintf('<img src="%s" alt="%s" %s />', $value,$title,join(' ',$add_opt));
                    }
                    break;
                 default:
                    $value = $this->feed->$func();
                    $value = $this->feed->get_title();
                    if (is_array($value))
                    {
                    	$tmp_value =$value[0];
                        unset($value);
                        $value = $tmp_value;
                    }
                    break;
            }
            if ($option != false)
            {
            	if ($option[0] == 'text')
                {
                	require_once($this->snip_path.'simplepie/class.html2text.inc');
                    $h2t = new \Html2Text\Html2Text($value);
                    // Simply call the get_text() method for the class to convert
                    // the HTML to the plain text. Store it into the variable.
                    $value = $h2t->get_text(); 
                }
                if (isset($option[1]))
                {
                    $len = intval($option[1]);
                    $value = mb_strimwidth($value, 0, $len,'...','UTF-8');
                }
            }
            return mb_convert_encoding($value,$modx->config['modx_charset'],'AUTO');
        }
        
        if ($func_name === 'feed_url') return $this->feed->feed_url;
        
        return $match[0];
    }

    // Item Parse Callback
    function item_parse_callback($match)
    {
    global $modx;
    
        if (count($match) > 2)
        {
        	$func_name = $match[2];
            $option = explode(',',$match[3]);
        }
        else
        {
        	$func_name = $match[1];
            $option = false;
        }
        
        if ($func_name == 'feed_url') return $this->feed->feed_url;
        
        $func = 'get_'.$func_name;
        if (preg_match('#^add_to_#',$func_name))
        {
        	$func = $func_name;
        }
        if (method_exists($this->feed_item,$func) == true) 
        {
        	switch ($func_name)
            {
            case 'date':
                if ($option == false)
                {
                	$format = $this->template_data['date'];
                }
                else
                {
                	$format = $option[0];
                }
                $value = $this->feed_item->get_local_date($format);
                $option = false;
                break;
            case 'permalink':
                $value = $this->feed_item->get_permalink();
                if (preg_match('@http://www\.bidders\.co\.jp/item/(\d+)@',$value,$matches))
                {
                    $value = 'http://www.bidders.co.jp/pitem/'.$matches[1].'/aff/'.BIDDERS_AFID.'/'.BIDDERS_LINKID.'/IT';
                }
                break;
            case 'author':
                $value = '';
                if ($author = $this->feed_item->get_author())
                {
                	$value = $author->get_name();
                }
                break;
            case 'category':
                $value = '';
                if ($category = $this->feed_item->{$func}())
                {
                	$value = $category->get_label();
                }
                break;
            case 'enclosure':
                if ($enclosure = $this->feed_item->{$func}())
                {
                	$value = $enclosure->native_embed($this->template_data['embed']);
                }
                else
                {
                	$value = '';
                }
                break;
            default:
                $value = $this->feed_item->{$func}();
                if (is_array($value))
                {
                	$tmp_value =$value[0];
                    unset($value);
                    $value = $tmp_value;
                }
                break;
            }
            if ($option != false)
            {
            	if ($option[0] == 'text')
                {
                    require_once($this->snip_path.'simplepie/class.html2text.inc');
                    $h2t = new \Html2Text\Html2Text($value);

                    // Simply call the get_text() method for the class to convert
                    // the HTML to the plain text. Store it into the variable.
                    $value = $h2t->get_text(); 
                }
                if (isset($option[1]))
                {
                    $len = intval($option[1]);
                    $value = mb_strimwidth($value, 0, $len,'...','UTF-8');
                }
            }
            return mb_convert_encoding($value,$modx->config['modx_charset'],'AUTO');
        }
        
        return $match[0];
    }
}
