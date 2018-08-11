<?php
/* ----------------------------------------------------------------
 * MODx RSS feed snippets use simplepie/class html2text
 * 
 * Created by ZeRo http://www.petit-power.com/
 * Modified by OpenGeek http://www.opengeek.com/ 
 * Create Date: 2006/12/21 
 * Last Modified: 2009/11/17
 * Version: 0.9
 *
 * required source:
 *   simplepie.inc ver 1.1.1- http://simplepie.org
 *   class.html2text.inc - http://www.chuggnutt.com/html2text.php
 *
 * Sinppets property:
 * &cache_enable=cache enable;list;true,false;false &cache_time=cache lefe time;text;60&extclass=extend item class name;text;&error=error template chunk name;text;&dateformat=date format;text;%Y/%m/%d %H:%i &max=max items;text;10&rows=pattern rows;text;0 &embed=embed property Chunk Name;text;
 *
 * [[pieX? &url=`FeedURL` &extclass=`hatena`&tpl=`template ChunkName`&max=10]]
 * 
 * --------------------------------------------------------------------------------------------
 */

if (!isset ($url) || $url == '') return '';

$param['cache_enable'] = $cache_enable=='true' ? true:false;
$param['cache_time']   = isset ($cache_time) ? intval($cache_time) :60;
$param['extclass']     = htmlentities($extclass,ENT_COMPAT,$modx->config['modx_charset']);
$param['tpl']          = isset ($tpl) ? $modx->getChunk($tpl) : file_get_contents(MODX_BASE_PATH . 'assets/snippets/piex/main.tpl');
$param['error']= isset ($error) ? $modx->getChunk($error) : 
'<p>[+feed_url+] is error.([+error+])</p>
';
$param['embedparam'] = $embed!='' ? $modx->getChunk($embed) : '';
$param['dateformat'] = isset($dateformat) ? $dateformat:'%Y/%m/%D';
$param['max'] = isset ($max) ? intval($max) : 10;
$param['rows'] = isset ($rows) ? intval($rows) : 0;
$url = html_entity_decode($url);
$url = explode(',',$url);
$param['feedURL'] = $url;

include_once(MODX_BASE_PATH . 'assets/snippets/piex/piex.class.inc.php');
$piex =  new PIEX();
$output = piex->execute($param);

return $output;

