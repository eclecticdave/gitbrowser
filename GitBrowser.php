<?php
// vim: set tabstop=2 shiftwidth=2: 

/**
*
* Git Browser
*
* Extension adds a custom tab to article views offering browsing of
* associated git repositories
*
* @category    Extensions
* @package 		GitBrowser
* @author			David Claughton <dave@eclecticdave.com>
* @copyright   Copyright (c) 2009 David Claughton
* @license     GNU General Public Licence 2.0 or later
*
* @subpackage  CustomTab
* @description Custom Tab boilerplate written by Rob Church
* @copyright   Copyright (c) Rob Church <robchur@gmail.com>
*
* @subpackage  git-php
* @description PHP front end to git repositories written by Zack Bartel
*              (including amendments cherry picked from forked version by Peeter Vois)
* @copyright   Copyright (c) 2006 Zack Bartel and Peeter Vois
* 
* +------------------------------------------------------------------------+
* | This program is free software; you can redistribute it and/or          |
* | modify it under the terms of the GNU General Public License            | 
* | as published by the Free Software Foundation; either version 2         | 
* | of the License, or (at your option) any later version.                 |
* |                                                                        |
* | This program is distributed in the hope that it will be useful,        |
* | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
* | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the          |
* | GNU General Public License for more details.                           |
* |                                                                        |
* | You should have received a copy of the GNU General Public License      |
* | along with this program; if not, write to the Free Software            |
* | Foundation, Inc., 59 Temple Place - Suite 330,                         |
* | Boston, MA  02111-1307, USA.                                           |
* +------------------------------------------------------------------------+ 
*/

#global $title;
global $repos;
global $git_embed;
#global $git_logo;

if (! defined('MEDIAWIKI')) {
	echo 'This file is an extension to the MediaWiki software and cannot be used standalone.';
	die;
}

$wgExtensionFunctions[] = 'efCustomActionSetup';

/**
* Extension setup function
*/
function efCustomActionSetup() {
	global $wgHooks, $wgMessageCache;
	$wgMessageCache->addMessage( 'codeaction', 'Code' );
	$wgHooks['SkinTemplateContentActions'][] = 'efCustomActionTab';
	$wgHooks['UnknownAction'][] = 'efCustomActionHandler';
}

/**
* Adds the custom action tab
*
* @param array $tabs
*/
function efCustomActionTab( &$tabs ) {
	global $wgTitle, $wgRequest;
	$action = $wgRequest->getText( 'action' );
	if( $wgTitle->getNamespace() != NS_SPECIAL ) {
		$tabs['code'] = array(
			'class' => $action == 'code' ? 'selected' : false,
			'text' => wfMsg( 'codeaction' ),
			'href' => $wgTitle->getLocalUrl( 'action=code' ),
		);
	}

	return true;
}

/**
* Handles the custom action
*
* @param string $action
* @param Article $article
*/
function efCustomActionHandler( $action, $article ) {
global $wgOut;
if( $action == 'code' ) {
	$wgOut->setPageTitle( $article->getTitle()->getText() );
	#$wgOut->addWikiText( "You are performing a custom action on '''" . $article->getTitle()->getText() . "'''." );
	$wgOut->addHTML( git_render_page() );
}

return false;
}

function git_render_page() {

	global $git_embed, $repos;

	$git_embed = true;

	/* Add the default css */
	$git_css = true;

	/* Add the git logo in the footer */
	$git_logo = true;

	$title  = "git";
	$repo_index = "index.aux";

	$repo_directory = '/home/david/git/';
	$geshi_directory = '/home/david/src/mediawiki/extensions/SyntaxHighlight_GeSHi/geshi';

	//if git is not installed into standard path, we need to set the path
	$mypath= getenv("PATH");
	$addpath = "/usr/lib/git-core";
	if (isset($mypath))
	{
		$mypath .= ":$addpath";
	}
	else
		$mypath = $addpath;
	putenv("PATH=$mypath");

	//repos could be made by an embeder script
	if (!is_array($repos))
		$repos = array();

	if (file_exists($repo_index))   {
		$r = file($repo_index);
		foreach ($r as $repo)
			$repos[] = trim($repo);
	}
	else if((file_exists($repo_directory)) && (is_dir($repo_directory))){
		if ($handle = opendir($repo_directory)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					/* TODO: Check for valid git repos */
					$repos[] = trim($repo_directory . $file);
				}
			}
			closedir($handle);
		} 
	}

	sort($repos);

	if ($geshi_directory != '') {
		require "$geshi_directory/geshi.php";
	}

	if (!isset($git_embed) && $git_embed != true)
		$git_embed = false;

	foreach ($_GET as $var=>$val)
	{
		$_GET[$var] = str_replace(";", "", $_GET[$var]);
	}

	$str = '';

	if (isset($_GET['dl']))
		if ($_GET['dl'] == 'targz') 
			write_targz(get_repo_path($_GET['p']));
		else if ($_GET['dl'] == 'zip')
			write_zip(get_repo_path($_GET['p']));
		else if ($_GET['dl'] == 'git_logo')
			write_git_logo();
		else if ($_GET['dl'] == 'plain')
			write_plain();
		else if ($_GET['dl'] == 'rss2')
			write_rss2();

	$str .= html_header($title);

	$str .= html_style($git_css);

	$str .= html_breadcrumbs();

	if (isset($_GET['p']))  { 
		$str .= html_spacer();
		$str .= html_desc($_GET['p']);
		$str .= html_spacer();
		$str .= html_summary($_GET['p']);
		$str .= html_spacer();
		if ($_GET['a'] == "commitdiff")
			$str .= html_diff($_GET['p'], $_GET['c'], $_GET['cp']);
		else
			$str .= html_browse($_GET['p']);
	}
	else {
		$str .= html_spacer();
		$str .= html_home($repos);
	}

	$str .= html_footer($git_logo);

	return $str;
}

function html_summary($proj)    {
	$str = '';

	$str .= '<div class="gitsummary">';

	if (isset($_GET['a']) && ($_GET['a'] == 'listheads')){
		$str .= html_title("Heads" . html_ahref(array('p'=>$proj), '[Summary]'));
		$str .= html_listheads($proj);
	}
	else {
		$repo = get_repo_path($proj);
		$branches = git_parse($repo, 'branches');
		$bmenu = "<ul id=\"ddmenu\">\n<li>Branches\n<ul>\n";
		foreach ($branches as $b => $h) {
			$a = html_ahref(array('p'=>$proj, 'h'=>$b), $b);
			$bmenu .= "<li>$a</li>\n";
		}
		$bmenu .= "</ul></li>";

		$bmenu .= <<< EOF
				<li>Menu2
					<ul>
						<li><a href="#">HTML Drop Down</a></li>
						<li><a href="#">DHTML Menu</a></li>
						<li><a href="#">JavaScript DropDown</a></li>
						<li><a href="#">Cascading Menu</a></li>
						<li><a href="#">CSS Horizontal Menu</a></li>
					</ul>
				</li>
				<li>Menu3
					<ul>
						<li><a href="#">ASP Dropdown</a></li>
						<li><a href="#">Pulldown menu</a></li>
						<li><a href="#">AJAX Drop Submenu</a></li>
						<li><a href="#">DIV Cascading Menu</a></li>
					</ul>
				</li>
			</ul>
EOF;

		#$str .= html_title("Summary" . html_ahref(array('p'=>$proj, 'a'=>'listheads'), '[Heads]') . $bmenu);
		$str .= html_title($bmenu);
		$head = $_GET['h'];
		if (!isset($head)) $head = 'master';

		if (!isset($_GET['c']))
			$str .= html_shortlog($proj, $head, 400, $_GET['sk']);
		else {
			$str .= html_logmsg($proj, $_GET['c']);
		}
	}

	$str .= '</div>';
	return $str;
}

function html_browse($proj) {
	$str = '';
 
	$c = isset($_GET['c']) ? $_GET['c'] : isset($_GET['h']) ? $_GET['h'] : 'HEAD';
	$f = $_GET['f'];

	$str .= '<div class="gitbrowse">';
	$str .= html_pathlinks($proj, $c, $f);
	if (isset($_GET['b'])) {
		$str .= html_blob($proj, $_GET['b'], $f);
	}
	else {
		$str .= html_tree($proj, $c, $f); 
	}
	$str .= '</div>';

	return $str;
}

function html_blob($proj, $blob, $filename)    {
	$str = '';

	$ext = pathinfo($filename, PATHINFO_EXTENSION);

	$repo = get_repo_path($proj);
	$out = array();
	$plain = html_ahref(array('p'=>$proj, 'dl'=>'plain', 'b'=>$blob), 'plain');
	$str .= "<div style=\"float:right;padding:7px;\">$plain</div>\n";
	exec("GIT_DIR=$repo git-cat-file blob $blob", &$out);
	$str .= "<div class=\"gitcode\">\n";
	$str .= geshi_format_code(implode("\n",$out), $ext);
	$str .= "</div>\n";

	return $str;
}

function html_diff($proj, $commit, $parent)    {
	$str = '';
	$str .= '<div class="gitbrowse">';

	$repo = get_repo_path($proj);
	$out = array();
	exec("GIT_DIR=$repo git-diff $parent $commit", &$out);
	$str .= "<div class=\"gitcode\">\n";
	$str .= geshi_format_code(implode("\n",$out), 'diff');
	$str .= "</div>\n";

	$str .= "</div>\n";
	return $str;
}

function html_tree($proj, $cid, $filepath)   {
	$str = '';

	$t = git_ls_tree(get_repo_path($proj), $cid, $filepath);

	$str .= "<div class=\"gittree\">\n";
	$str .= "<table>\n";
	foreach ($t as $obj) {
		$plain = "";
		$perm = perm_string($obj['perm']);
		$f = (isset($filepath)) ? "$filepath/{$obj['file']}" : $obj['file'];
		if ($obj['type'] == 'tree')
			$objlink = html_ahref(array('p'=>$proj, 'c'=>$cid, 'f'=>$f), $obj['file']);
		else if ($obj['type'] == 'blob')    {
			$plain = html_ahref(array('p'=>$proj, 'dl'=>'plain', 'c'=>$cid, 'b'=>$obj['hash']), 'plain');
			$objlink = html_ahref(array('p'=>$proj, 'c'=>$cid, 'b'=>$obj['hash'], 'f'=>$f), $obj['file'], 'blob');
		}

		$str .= "<tr><td>$perm</td><td>$objlink</td><td>$plain</td></tr>\n";
	}
	$str .= "</table>\n";
	$str .= "</div>\n";

	return $str;
}

function html_shortlog($proj, $head, $count, $skip=0) {
	$str = '';

	$str .= "<table>\n";
	$repo = get_repo_path($proj);
	$c = git_commit($repo, $head, $skip);
	for ($i = 0; $i < $count && $c; $i++)  {
		$date = date("D n/j/y G:i", (int)$c['date']);
		$cid = $c['commit_id'];
		$pid = $c['parent'][0];
		$mess = html_ahref(array('p'=>$proj, 'a'=>'commitdiff', 'c'=>$cid, 'cp'=>$pid), short_desc($c['message'], 110), 'shortlog');
		$branches = $c['branches'];
		$str .= "<tr><td>$date</td><td>{$c['author']}</td><td>$branches</td><td>$mess</td></tr>\n"; 
		$c = git_commit($repo, $pid);
	}
	$str .= "</table>\n";

	//TODO: Next should not be a link if there are no more revisions
	$next = html_ahref(array('p'=>$proj, 'sk'=>$skip+20), 'Next');
	$prev = ($skip >= 20) ? html_ahref(array('p'=>$proj, 'sk'=>$skip-20), 'Prev') : 'Prev';
	$str .= "<table>\n";
	$str .= "<tr><td>$prev</td><td>$next</td><tr>\n";
	$str .= "</table>\n";

	return $str;
}

function html_logmsg($proj, $cid)   {
	$str = '';

	$repo = get_repo_path($proj);
	$c = git_commit($repo, $cid);
	$pid = $c['parent'][0];
	$blob = $_GET['b'];

	$diff = html_ahref(array('p'=>$proj, 'a'=>'commitdiff', 'c'=>$cid, 'cp'=>$pid), $cid);
	$tree = html_ahref(array('p'=>$proj, 'a'=>'jump_to_tag', 'c'=>$cid), $cid);
	$parent = html_ahref(array('p'=>$proj, 'a'=>'jump_to_tag', 'c'=>$pid), $pid);
	$diff_or_tree = ($_GET['a'] == "commitdiff") ? $tree : $diff;

	$str .= "<div class=\"gitsha1\">\n";
	$str .= "<table>\n";
	$str .= "<tr><td>Commit</td><td>$diff_or_tree</td></tr>\n";
	if (isset($pid))
		$str .= "<tr><td>Parent</td><td>$parent</td></tr>\n";
	if (isset($blob))
		$str .= "<tr><td>Blob</td><td>$blob</td></tr>\n";
	$str .= "</table></div>\n";

	$str .= "<table>\n";
	$date = date("D n/j/y G:i", (int)$c['date']);
	$mess = $c['message'];
	$str .= "<tr><td>$date</td><td>{$c['author']}</td></tr>\n"; 
	$str .= "</table>\n";
	$str .= "<pre>$mess</pre>\n";

	return $str;
}

function html_listheads($proj) {
	$str = '';

	$repo = get_repo_path($proj);
	$heads = git_parse($repo, 'branches');

	$str .= "<table>\n";
	foreach ($heads as $n => $h)  {
		$c = git_commit($repo, $h);
		$date = date("D n/j/y G:i", (int)$c['date']);
		$cid = $c['commit_id'];
		$pid = $c['parent'][0];
		$mess = html_ahref(array('p'=>$_GET['p'], 'a'=>'commitdiff', 'c'=>$cid, 'cp'=>$pid), short_desc($c['message'], 110), 'shortlog');
		$str .= "<tr><td>$date</td><td>{$c['author']}</td><td>$mess</td><td>$n</td></tr>\n"; 
	}
	$str .= "</table>\n";

	return $str;
}

function html_desc($proj)    {
	$str = '';
	$str .= '<div class="gitdesc">';	
	$repo = get_repo_path($proj);
	$desc = file_get_contents("$repo/description"); 
	$owner = get_file_owner($repo);
	$last =  get_last($repo);

	$str .= "<table>\n";
	$str .= "<tr><td>description</td><td>$desc</td></tr>\n";
	$str .= "<tr><td>owner</td><td>$owner</td></tr>\n";
	$str .= "<tr><td>last change</td><td>$last</td></tr>\n";
	$str .= "</table>\n";

	$str .= "</div>\n";
	return $str;
}

function html_home($repos)    {

	$str = '';
	$str .= "<table>\n";
	$str .= "<tr><th>Project</th><th>Description</th><th>Owner</th><th>Last Changed</th><th>Download</th></tr>\n";
	foreach ($repos as $repo)   {
		$desc = short_desc(file_get_contents("$repo/description")); 
		$owner = get_file_owner($repo);
		$last =  get_last($repo);
		$proj = get_project_link($repo);
		$dlt = get_project_link($repo, "targz");
		$dlz = get_project_link($repo, "zip");
		$str .= "<tr><td>$proj</td><td>$desc</td><td>$owner</td><td>$last</td><td>$dlt | $dlz</td></tr>\n";
	}
	$str .= "</table>";
	return $str;
}

function html_header($title)  {
	global $git_embed;

	$str = '';
	
	if (!$git_embed)    {
		$str .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n";
		$str .= "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\">\n";
		$str .= "<head>\n";
		$str .= "\t<title>$title</title>\n";
		$str .= "\t<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"/>\n";
		$str .= "\t<link href=\"prettify.css\" type=\"text/css\" rel=\"stylesheet\" />\n";
		$str .= "\t<script type=\"text/javascript\" src=\"prettify.js\"></script>\n";
		$str .= "</head>\n";
		$str .= "<body onload=\"prettyPrint()\">\n";
	}
	/* Add rss2 link */
	if (isset($_GET['p']))  {
		$str .= "<link rel=\"alternate\" title=\"{$_GET['p']}\" href=\"".sanitized_url()."p={$_GET['p']}&dl=rss2\" type=\"application/rss+xml\" />\n";
	}
	$str .= "<div id=\"gitbody\">\n";

	return $str;
}

function write_git_logo()   {

	$git = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52" .
	"\x00\x00\x00\x48\x00\x00\x00\x1b\x04\x03\x00\x00\x00\x2d\xd9\xd4" .
	"\x2d\x00\x00\x00\x18\x50\x4c\x54\x45\xff\xff\xff\x60\x60\x5d\xb0" .
	"\xaf\xaa\x00\x80\x00\xce\xcd\xc7\xc0\x00\x00\xe8\xe8\xe6\xf7\xf7" .
	"\xf6\x95\x0c\xa7\x47\x00\x00\x00\x73\x49\x44\x41\x54\x28\xcf\x63" .
	"\x48\x67\x20\x04\x4a\x5c\x18\x0a\x08\x2a\x62\x53\x61\x20\x02\x08" .
	"\x0d\x69\x45\xac\xa1\xa1\x01\x30\x0c\x93\x60\x36\x26\x52\x91\xb1" .
	"\x01\x11\xd6\xe1\x55\x64\x6c\x6c\xcc\x6c\x6c\x0c\xa2\x0c\x70\x2a" .
	"\x62\x06\x2a\xc1\x62\x1d\xb3\x01\x02\x53\xa4\x08\xe8\x00\x03\x18" .
	"\x26\x56\x11\xd4\xe1\x20\x97\x1b\xe0\xb4\x0e\x35\x24\x71\x29\x82" .
	"\x99\x30\xb8\x93\x0a\x11\xb9\x45\x88\xc1\x8d\xa0\xa2\x44\x21\x06" .
	"\x27\x41\x82\x40\x85\xc1\x45\x89\x20\x70\x01\x00\xa4\x3d\x21\xc5" .
	"\x12\x1c\x9a\xfe\x00\x00\x00\x00\x49\x45\x4e\x44\xae\x42\x60\x82";
	
	header("Content-Type: img/png");
	header("Expires: +1d");
	echo $git;
	die();
}

function html_footer($git_logo)  {
	global $git_embed;

	$str = '';

	$str .= "<div class=\"gitfooter\">\n";

	if (isset($_GET['p']))  {
		$str .= html_ahref(array('p'=>$_GET['p'], 'dl'=>'rss2'), 'RSS', 'rss_logo');
	}

	if ($git_logo)    {
		$str .= "<a href=\"http://www.kernel.org/pub/software/scm/git/docs/\">" . 
			 "<img src=\"".sanitized_url()."dl=git_logo\" style=\"border-width: 0px;\"/></a>\n";
	}

	$str .= "</div>\n";
	$str .= "</div>\n";
	if (!$git_embed)    {
		$str .= "</body>\n";
		$str .= "</html>\n";
	}

	return $str;
}

function git_tree($gitdir, $tree) {
	$out = array();
	$command = "GIT_DIR=$gitdir git-ls-tree --name-only $tree";
	exec($command, &$out);
}

function get_git($repo) {
	if (file_exists("$repo/.git"))
		$gitdir = "$repo/.git";
	else
		$gitdir = $repo;
	return $gitdir;
}

function get_file_owner($path)  {
	$s = stat($path);
	$pw = posix_getpwuid($s["uid"]);
	return preg_replace("/[,;]/", "", $pw["gecos"]);
//        $out = array();
//        $own = exec("GIT_DIR=$path git-rev-list --header --max-count=1 HEAD | grep -a committer | cut -d' ' -f2-3" ,&$out);
//        return $own;
}

function get_last($repo)    {
	$out = array();
	$date = exec("GIT_DIR=$repo git-rev-list --header --max-count=1 HEAD | grep -a committer | cut -f5-6 -d' '", &$out);
	return date("D n/j/y G:i", (int)$date);
}

function get_project_link($repo, $type = false)    {
	$path = basename($repo);
	if (!$type)
		return html_ahref(array('p'=>$path), $path);
	else if ($type == "targz")
		return html_ahref(array('p'=>$path, 'dl'=>'targz'), '.tar.gz');
	else if ($type == "zip")
		return html_ahref(array('p'=>$path, 'dl'=>'zip'), '.zip');
}

function git_commit($repo, $cid, $skip=0)  {
	$out = array();
	$commit = array();

	if (strlen($cid) <= 0)
		return 0;

	$skip_p = ($skip > 0) ? "--skip=$skip" : '';

	exec("GIT_DIR=$repo git-rev-list  --header --max-count=1 $skip_p $cid", &$out);
	$commit['commit_id'] = $out[0];
	$commit['parent'] = array();

	$size = count($out);
	$inmsg = 0;
	for ($i = 1; $i < $size-1; $i++) {
		if ($out[$i] == '') $inmsg = 1;
		if ($inmsg == 0) {
			$g = explode(" ", $out[$i]);
			if ($g[0] == 'parent')
				$commit['parent'][] = $g[1];
			else if ($g[0] == 'author') {
				for ($j = 1; $g[$j][0] != '<' && $j < 5; $j++)   {
					$commit["author"] .= "{$g[$j]} ";
				}
				$commit["date"] = "{$g[++$j]} {$g[++$j]}";
			}
			else 
				$commit[$g[0]] = $g[1];
		}
		else
			$commit["message"] .= $out[$i] . "\n";
	}		

	$out = array();
	exec("GIT_DIR=$repo git-branch --contains " . $commit['commit_id'], &$out);

	$size = count($out);
	for($i = 0; $i < $size; $i++) {
		$out[$i] = substr($out[$i], 2);
	}
	$commit['branches'] = implode(', ', $out);

	return $commit;
}

function get_repo_path($proj)   {
	global $repos;

	foreach ($repos as $repo)   {
		$path = basename($repo);
		if ($path == $proj)
			return $repo;
	}
}

function git_ls_tree($repo, $cid, $path) {
	$ary = array();
		
	$out = array();

	if (isset($path)) $path .= '/';
	$pathlen = strlen($path);

	//Have to strip the \t between hash and file
	exec("GIT_DIR=$repo git-ls-tree $cid $path | sed -e 's/\t/ /g'", &$out);

	foreach ($out as $line) {
		$entry = array();
		$arr = explode(" ", $line);
		$entry['perm'] = $arr[0];
		$entry['type'] = $arr[1];
		$entry['hash'] = $arr[2];
		$entry['file'] = substr($arr[3],$pathlen);
		$ary[] = $entry;
	}
	return $ary;
}

/* TODO: cache this */
function sanitized_url()    {
	global $git_embed;

	/* the sanitized url */
	$url = "{$_SERVER['SCRIPT_NAME']}?";

	if (!$git_embed)    {
		return $url;
	}

	/* the GET vars used by git-php */
	$git_get = array('p', 'dl', 'a', 'b', 'c', 'cp', 'f', 'sk', 'h');

	foreach ($_GET as $var => $val) {
		if (!in_array($var, $git_get))   {
			$get[$var] = $val;
			$url.="$var=$val&amp;";
		}
	}
	return $url;
}

function write_plain()  {
	$repo = get_repo_path($_GET['p']);
	$blob = $_GET['b'];
	header("Content-Type: text/plain");
	$str = system("GIT_DIR=$repo git-cat-file blob $blob");
	echo $str;
	die();
}

function write_targz($repo) {
	$p = basename($repo);
	$proj = explode(".", $p);
	$proj = $proj[0]; 
	exec("cd /tmp && git-clone $repo && rm -Rf /tmp/$proj/.git && tar czvf $proj.tar.gz $proj && rm -Rf /tmp/$proj");
	
	$filesize = filesize("/tmp/$proj.tar.gz");
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
	header("Content-Type: application/x-tar-gz");
	header("Content-Length: " . $filesize);
	header("Content-Disposition: attachment; filename=\"$proj.tar.gz\";" );
	echo file_get_contents("/tmp/$proj.tar.gz");
	die();
}

function write_zip($repo) {
	$p = basename($repo);
	$proj = explode(".", $p);
	$proj = $proj[0]; 
	exec("cd /tmp && git-clone $repo && rm -Rf /tmp/$proj/.git && zip -r $proj.zip $proj && rm -Rf /tmp/$proj");
	
	$filesize = filesize("/tmp/$proj.zip");
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
	header("Content-Type: application/x-zip");
	header("Content-Length: " . $filesize);
	header("Content-Disposition: attachment; filename=\"$proj.zip\";" );
	echo file_get_contents("/tmp/$proj.zip");
	die();
}

function write_rss2()   {
	$proj = $_GET['p'];
	$repo = get_repo_path($proj);
	$link = "http://{$_SERVER['HTTP_HOST']}".sanitized_url()."p=$proj";
	$c = git_commit($repo, "HEAD");

	header("Content-type: text/xml", true);
	
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	?>
	<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	>

	 
	<channel>
		<title><?php echo $proj ?></title>
		<link><?php echo $link ?></link>
		<description><?php echo $proj ?></description>
		<pubDate><?php echo date('D, d M Y G:i:s', $c['date'])?></pubDate>
		<generator>http://code.google.com/p/git-php/</generator>
		<language>en</language>
		<?php for ($i = 0; $i < 10 && $c; $i++): ?>
		<item>
			<title><?php echo $c['message'] ?></title>
			<link><?php echo $link?></link>
			<pubDate><?php echo date('D, d M Y G:i:s', $c['date'])?></pubDate>
			<guid isPermaLink="false"><?php echo $link ?></guid>
			<description><?php echo $c['message'] ?></description>
			<content><?php echo $c['message'] ?></content>
		</item>
		<?php $c = git_commit($repo, $c['parent']);
			$link = "http://{$_SERVER['HTTP_HOST']}".sanitized_url()."p=$proj&amp;a=commitdiff&amp;c={$c['commit_id']}&amp;hb={$c['parent']}";
				endfor;
		?>
	</channel>
	</rss>
	<?php
	die();
}

function perm_string($perms)    {

	//This sucks
	switch ($perms) {
		case '040000':
			return 'drwxr-xr-x';
		case '100644':
			return '-rw-r--r--';
		case '100755':
			return '-rwxr-xr-x';
		case '120000':
			return 'lrwxrwxrwx';

		default:
			return '----------';
	}
}

function short_desc($desc, $size=25)  {
	$trunc = false;
	$short = "";
	$d = explode(" ", $desc);
	foreach ($d as $str)    {
		if (strlen($short) < $size)
			$short .= "$str ";
		else    {
			$trunc = true;
			break;
		}
	}

	if ($trunc)
		$short .= "...";

	return $short;
}

function html_spacer($text = "&nbsp;")  {
	return "<div class=\"gitspacer\">$text</div>\n";
}

function html_title($text = "&nbsp;")  {
	$str = '';

	$str .= "<div class=\"gittitle\">$text\n";
	$str .= "</div>\n";

	return $str;
}

function html_pathlinks($proj, $cid, $path) {
	$str = '';

	$str .= ($path != '') ? html_ahref(array('p'=>$proj, 'c'=>$cid), $proj) : $proj;

	$dirs = explode('/', $path);
	$base = array_pop($dirs);
	$f = '';
	foreach ($dirs as $d) {
		$f .= ($f == '') ? $d : "/$d";
		$str .= ' / ' . html_ahref(array('p'=>$proj, 'c'=>$cid, 'f'=>$f), $d);
	}
	$str .= " / $base";

	return html_title($str);
}

function html_breadcrumbs()  {
	$str = '';

	$str .= "<div class=\"githead\">\n";
	$crumb = "<a href=\"".sanitized_url()."\">projects</a> / ";

	if (isset($_GET['p']))
		$crumb .= html_ahref(array('p'=>$_GET['p']), $_GET['p']) . " / ";

	if ($_GET['a'] == 'commitdiff')
		$crumb .= 'commitdiff';
	else if (isset($_GET['b']))
		$crumb .= "blob";

	$str .= $crumb;
	$str .= "</div>\n";

	return $str;
}

function html_style($git_css) {
	$str = '';

	if (file_exists("style.css"))
		$str .= "<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />\n";
	if (!$git_css)
		return $str;

	else    {
	$str .= "<style type=\"text/css\">\n";
	$str .= <<< EOF
		#gitbody    {
			margin: 10px 10px 10px 10px;
			font-family: sans-serif;
			font-size: 90%;
		}

		div.githead    {
			margin: 0px 0px 0px 0px;
			padding: 10px 10px 10px 10px;
			background-color: #d9d8d1;
			font-weight: bold;
			font-size: 18px;
		}

		#gitbody th {
			text-align: left;
			padding: 0px 0px 0px 7px;
		}

		#gitbody td {
			padding: 5px 0px 0px 7px;
		}

		div.gitsummary tr:hover { background-color:#edece6; }
		div.gitbrowse tr:hover { background-color:#edece6; }

		div.gittree a.blob, a.shortlog {
			text-decoration: none;
			color: #000000;
		}

		div.gitsummary, div.gitbrowse, div.gitdesc {
			padding: 10px;
			border: 1px solid grey;
		}

		div.gitsummary pre {
			border: 0px;
			background-color: white;
			font-size: 110%;
		}

		div.gitsummary div.gitsha1 {
			float:right;
			border-left: 1px solid grey;
			font-size: 80%;
		}

		div.gitsha1 table {
			padding: 0px;
			margin: 0px;
		}

		div.gitcode {
			padding: 10px;
			font-size: 110%;
		}

		div.gitspacer   {
			padding: 1px 0px 0px 0px;
			background-color: #FFFFFF;
		}

		div.gitfooter {
			padding: 7px 2px 2px 2px;
			background-color: #d9d8d1;
			text-align: right;
		}

		div.gittitle   {
			padding: 7px 7px 7px 7px;
			background-color: #d9d8d1;
			font-weight: bold;
		}

		div.gittree a.blob:hover, a.shortlog:hover {
			text-decoration: underline;
		}
		a.gittree:hover { text-decoration:underline; color:#880000; }
		a.rss_logo {
			float:left; padding:3px 0px; width:35px; line-height:10px;
				margin: 2px 5px 5px 5px;
				border:1px solid; border-color:#fcc7a5 #7d3302 #3e1a01 #ff954e;
				color:#ffffff; background-color:#ff6600;
				font-weight:bold; font-family:sans-serif; font-size:10px;
				text-align:center; text-decoration:none;
			}
		a.rss_logo:hover { background-color:#ee5500; }

		#ddmenu ul {
			margin: 0;
			padding: 0;
			list-style: none;
		}

		#ddmenu li {
			display: inline;
			position: relative;
			width: 20em;
		}

		#ddmenu li ul {
			display: none;
			position: absolute;
			top: 1em;
			left: 0;
			background-color: #d9d8d1;
		}

		#ddmenu li:hover ul {
			display: block;
		}
EOF;

	$str .= "</style>\n";
	}

	return $str;
}

function geshi_format_code($text, $ext)
{
	$str = '';
	
	$geshi = new GeSHi($text, '');
	$lang = ($ext == 'diff') ? $ext : $geshi->get_language_name_from_extension($ext);
	$geshi->set_language($lang);
	if( $geshi->error() == GESHI_ERROR_NO_SUCH_LANG )
	return null;
	$geshi->set_encoding( 'UTF-8' );
	//$geshi->enable_classes();
	//$geshi->set_overall_class( "source-$lang" );
	//$geshi->enable_keyword_links( false );
  $geshi->enable_line_numbers( GESHI_FANCY_LINE_NUMBERS );
	$geshi->set_header_type(GESHI_HEADER_DIV);

	$str .= $geshi->parse_code($text,1);
	if ($geshi->error())
	$str .= $geshi->error();
	return $str;
}

function html_ahref( $arguments, $text="", $class="" )
{
	$ahref = "<a ";
	if( $class != "" ) $ahref .= "class=\"$class\" ";
	$ahref .= "href=\"";
	$ahref .= sanitized_url();

	foreach( $arguments as $key => $val ){
    if( $val != "" ) $ahref .= "$key=$val&";
	}

	$ahref = substr($ahref,0,-1);
	$ahref .= "\">";
	if ($text != "") $ahref .= $text . "</a>\n";
	return $ahref;
}

function git_parse($repo, $what ){
	$cmd1="GIT_DIR=$repo git-rev-parse --symbolic --$what";
	$out1 = array();
	$bran=array();
	exec( $cmd1, &$out1 );
  for( $i=0; $i < count( $out1 ); $i++ ){
		if ($out1 == '') continue;
	  $cmd2="GIT_DIR=$repo git-rev-list ";
    $cmd2 .= "--max-count=1 ".escapeshellarg($out1[$i]);
		$out2 = array();
		exec( $cmd2, &$out2 );
		if ($out2[0] != '')
			$bran[$out1[$i]] = $out2[0];
	}
	return  $bran;
}

?>
