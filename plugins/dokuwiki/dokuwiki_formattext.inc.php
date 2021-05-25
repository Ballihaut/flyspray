<?php
class dokuwiki_TextFormatter
{    
    static function render($text, $type = null, $id = null, $instructions = null)
    {
        global $conf, $baseurl, $db, $fs;
        
        // Unfortunately dokuwiki also uses $conf
        $fs_conf = $conf;
        $conf = array();

        // Dokuwiki generates some notices
        error_reporting(E_ALL ^ E_NOTICE);
        if (!$instructions) {
            include_once(BASEDIR . '/plugins/dokuwiki/inc/parser/parser.php');
        }
        require_once(BASEDIR . '/plugins/dokuwiki/inc/common.php');
        require_once(BASEDIR . '/plugins/dokuwiki/inc/parser/xhtml.php');
        

        // Create a renderer
        $Renderer = new Doku_Renderer_XHTML();

        if (!is_string($instructions) || strlen($instructions) < 1) {
            $modes = p_get_parsermodes();
            
            $Parser = new Doku_Parser();
            
            // Add the Handler
            $Parser->Handler = new Doku_Handler();
            
            // Add modes to parser
            foreach($modes as $mode){
                $Parser->addMode($mode['mode'], $mode['obj']);
            }
            $instructions = $Parser->parse($text);

            
            // Cache the parsed text
            if (!is_null($type) && !is_null($id)) {
                $fields = array('content'=> serialize($instructions), 'type'=> $type , 'topic'=> $id,
                                'last_updated'=> time());

                $keys = array('type','topic');
                //autoquote is always true on db class
                $db->Replace('{cache}', $fields, $keys);
            }
        } else {
            $instructions = unserialize($instructions);
        }

        $Renderer->smileys = getSmileys();
        $Renderer->entities = getEntities();
        $Renderer->acronyms = getAcronyms();
        $Renderer->interwiki = getInterwiki();
        
	$conf = $fs_conf;
	$conf['relnofollow']= $fs->prefs['relnofollow']; # ugly workaround
        $conf['cachedir'] = FS_CACHE_DIR; // for dokuwiki
        $conf['fperm'] = 0600;
        $conf['dperm'] = 0700;
        
        // Loop through the instructions
        foreach ($instructions as $instruction) {
            // Execute the callback against the Renderer
            call_user_func_array(array(&$Renderer, $instruction[0]), $instruction[1]);
        }

        $return = $Renderer->doc;
		
        // Display the output
        if (Get::val('string')) {
            $words = explode(' ', Get::val('string'));
            foreach($words as $word) {
                $return = html_hilight($return, $word);
            }
        }
        
        return $return;
    }
    static function textarea( string $name, $rows, $cols, $attrs = null, $content = null) : string {
    	
    	$name = htmlspecialchars($name, ENT_QUOTES, 'utf-8');
        $rows = intval($rows);
        $cols = intval($cols);
        $return = '<div class="dokuwiki_toolbar">'
        		. dokuwiki_TextFormatter::getDokuWikiToolbar( $attrs['id'] )
        		. '</div>';
        
        $return .= "<textarea name=\"{$name}\" cols=\"$cols\" rows=\"$rows\" ";
        if (is_array($attrs)) {
            $return .= join_attrs($attrs);
        }
        $return .= '>';
        if (!is_null($content)) {
            $return .= htmlspecialchars($content, ENT_QUOTES, 'utf-8');
        }
        $return .= '</textarea>';
        return $return;
    }

	/**
	 * Displays a toolbar for formatting text in the DokuWiki Syntax
	 * Uses Javascript
	 *
	 * @param string $textareaId
	 */
	static function getDokuWikiToolbar( string $textareaId ) : string {
		global $conf, $baseurl;
		$out='';
		
		$out.='<a tabindex="-1" title="'.eL('editorbold').'" href="javascript:void(0);" onclick="surroundText(\'**\', \'**\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/format-text-bold.png" alt="Bold" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editoritalic').'" href="javascript:void(0);" onclick="surroundText(\'//\', \'//\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/format-text-italic.png" alt="Italics" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editorunderline').'" href="javascript:void(0);" onclick="surroundText(\'__\', \'__\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/format-text-underline.png" alt="Underline" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editorstrikethrough').'" href="javascript:void(0);" onclick="surroundText(\'&lt;del&gt;\', \'&lt;/del&gt;\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/format-text-strikethrough.png" alt="Strikethrough" /></a>';
		$out.='<img src="'.$baseurl.'plugins/dokuwiki/img/divider.gif" align="bottom" alt="|" style="margin: 0 3px 0 3px;" />';
		$out.='<a tabindex="-1" title="'.eL('editorh1').'" href="javascript:void(0);" onclick="surroundText(\'======\', \'======\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/h1.gif" width="23" height="22" alt="H1" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editorh2').'" href="javascript:void(0);" onclick="surroundText(\'=====\', \'=====\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/h2.gif" width="23" height="22" alt="H2" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editorh3').'" href="javascript:void(0);" onclick="surroundText(\'====\', \'====\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/h3.gif" width="23" height="22" alt="H3" /></a>';
		$out.='<img title="Divider" src="'.$baseurl.'plugins/dokuwiki/img/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />';

		/* hide embed syntax until the 'fetch.php issue' is solved or an alternative is implemented
		$out.='<a tabindex="-1" title="'.eL('editorimage').'" href="javascript:void(0);" onclick="surroundText(\'&#123;&#123;http://\', \'&#125;&#125;\', \''.$textareaId.'\'); return false;">
			<img src="'.$baseurl.'plugins/dokuwiki/img/image-x-generic.png" alt="image" /></a>';
		*/

		$out.='<a tabindex="-1" title="'.eL('editorunorderedli').'" href="javascript:void(0);" onclick="replaceText(\'\n  * \', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/ul.gif" width="23" height="22" alt="ul" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editororderedli').'" href="javascript:void(0);" onclick="replaceText(\'\n  - \', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/ol.gif" width="23" height="22" alt="ol" /></a>';
		$out.='<a tabindex="-1" title="'.eL('editorhorizontalrule').'" href="javascript:void(0);" onclick="replaceText(\'----\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/hr.gif" width="23" height="22" alt="hr" /></a>';
		$out.='<img src="'.$baseurl.'plugins/dokuwiki/img/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />';
		$out.='<a tabindex="-1" title="'.eL('editorlink').'" href="javascript:void(0);" onclick="surroundText(\'[[http://example.com|External Link\', \']]\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/text-html.png" alt="Link" /></a>';

		/* emailicon for a generic link and a globe for today ftp is a bit unpopular: seems not the most important button/syntax on flyspray's default dokuwiki toolbar
		$out.='<a tabindex="-1" title="Insert Email" href="javascript:void(0);" onclick="surroundText(\'[[\', \']]\', \''.$textareaId.'\'); return false;">
			<img src="'.$baseurl.'plugins/dokuwiki/img/email.png" alt="Insert Email" border="0" /></a>';
		$out.='<a tabindex="-1" href="javascript:void(0);" onclick="surroundText(\'[[ftp://\', \']]\', \''.$textareaId.'\'); return false;">
			<img src="'.$baseurl.'plugins/dokuwiki/img/network.png" alt="Insert FTP Link" title="Insert FTP Link" border="0" /></a>';
				
		$out.='<img src="'.$baseurl.'plugins/dokuwiki/img/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />';
		*/
		
		$out.='<a tabindex="-1" title="'.eL('editorcode').'" href="javascript:void(0);" onclick="surroundText(\'<code>\', \'</code>\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/source.png" alt="Code" /></a>';
		/*
		$out.='<a tabindex="-1" title="'.eL('editorcodesyntax).'" href="javascript:void(0);" onclick="surroundText(\'<code php>\', \'</code>\', \''.$textareaId.'\'); return false;"><img src="'.$baseurl.'plugins/dokuwiki/img/source_php.png" alt="Code" /></a>';
		*/

		# IDEA/TODO: list of available languages for syntax highlighting, dropdownlist or cheatsheet similiar to the wikicheatsheet below
		# IDEA/TODO: smiley selector similiar to the wikicheatsheet below.
		
		/*
		# wikicheatsheet
		# Maybe move the generic CSS (without $textareaID) to a dokuwiki.css or something like that.
		$out.='
<style>
#'.$textareaId.' {position:relative;}
.wikicheatsheet { display:inline-block;margin-left:1em;}
.wikicheatsheet label{ color:#aaa;}
.wikicheatsheet td { font-family: monospace;}
.wikicheatsheet div{ display:none;position:absolute;left:1em;background-color:#fff;z-index:10;padding:1em;border:1px solid #ccc;}
.wikicheatsheet label:hover ~ div{ display:block;}
</style>';

		$out.='
<div class="wikicheatsheet">
<label for="wikicheatsheet_'.$textareaId.'"><i class="fa fa-info-circle fa-2x"></i></label>
<div id="wikicheatsheet_'.$textareaId.'">
<table>
<tbody>
<tr><th>'.L('editorlink').'</th><td>[[https://www.flyspray.org|Flyspray]]</td></tr>
<tr><th>'.L('editorquote').'</th><td>&gt; a quote</td></tr>
<tr><th>'.L('editorcode').'</th><td>&lt;code&gt;preformatted monospace text&lt;code&gt;</td></tr>
<tr><th>'.L('editorcodesyntax').'</th><td>&lt;code php&gt;echo "Helloworld";&lt;code&gt;</td></tr>
</tbody>
</table>
</div>
</div>';
		*/
		
		return $out;
	}
}
?>
