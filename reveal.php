<?php

$__DIR__ = dirname(__FILE__);

$PC = array(
	'CACHE_FILE' => $__DIR__.'/PC.reveal.cache.php',
);

include_once realpath($__DIR__.'/../../PC/init.php');


/**
 * Reveal
 *
 * @author beShy <i@beshy.net>
 * @created 2014-10-22 15:33
 * @lastupdated 2014-10-29 10:42
 */
Class Reveal
{ // BEGIN class Reveal
	public $__DIR__=null;

	public $cnf=array();
	public $LOG=null;
	public $DB=null;
	public $CDB=null;
	public $CDB2=null;

	public $locks = array();

	public $site=null;
	public $siteCnf=array();
	public $lastCacheDatas=array();
	public $tmpFiles=array();

	/**
	 * Constructor
	 */
	function __construct( $cnf=array(), $L=null )
	{ // BEGIN __constructor
		$this->__DIR__ = dirname(__FILE__).DIRECTORY_SEPARATOR;
		$c = include $this->__DIR__.'config.php';
		$this->cnf = $this->cnf + $c + $cnf;
		$C =& $this->cnf;

		$this->LOG = is_null($L) ? new Log($C['log_config']) : $L;



		@$this->DB=new Couchbase($C['db_url']);
		if(@$this->DB->getResultCode() !== COUCHBASE_SUCCESS){ 
			$this->dumpError('cache error', 'cache error');
			exit;
		}

		if ( !empty($C['data_struct']) && $C['data_struct'] == 'json' ) {
			$this->DB->setOption(COUCHBASE_OPT_SERIALIZER, COUCHBASE_SERIALIZER_JSON_ARRAY);
		}

		@$this->CDB=new Couchbase($C['cdb'][0],$C['cdb'][1],$C['cdb'][2],$C['cdb'][3]);

		if(@$this->CDB->getResultCode() !== COUCHBASE_SUCCESS){ 
			$this->dumpError('cache error', 'cache error');
			exit;
		}

		@$this->CDB2=new Couchbase($C['cdb2'][0],$C['cdb2'][1],$C['cdb2'][2],$C['cdb2'][3]);
		if(@$this->CDB2->getResultCode() !== COUCHBASE_SUCCESS){ 
			$this->dumpError('cache error', 'cache error');
			exit;
		}


		$tmpDir = $C['vpos_path'];
		if (!file_exists($tmpDir))
		{
			mkdir($tmpDir);
		}
	} // END __constructor


	/**
	 * __destruct
	 *
	 * @return	void
	 */
	function __destruct(  )
	{ // BEGIN __destruct
		$C =& $this->cnf;
		$L = $this->LOG;

		//$L->trace('reveal __destruct');

		//$this->cacheDatas();
		$this->unsetLocks();

		$ntime = explode(" ", microtime());
		$ptime = explode(" ", PC_BEGIN_TIME);
		$time = sprintf('%01.2f', (($ntime[1] - $ptime[1]) + ($ntime[0] - $ptime[0])));

		

		if (!empty($C['del_tmp_file']))
			$this->delTmpFiles();

		if (!$C['debug']) {
			$L->LOG = array();
			$ip = empty($_SERVER['REMOTE_ADDR']) ? '-' : $_SERVER['REMOTE_ADDR'] ;
			$L->trace("{$C['mode']} $time {$_SERVER['REMOTE_ADDR']} {$C['id']} {$C['url']} ".http_build_query($C['params']));
		} else {
			$L->trace('spend time: '.$time);
		}

		// if ($C['mode'] == 'getOrigSrcs') {
		// 	$L->LOG = array();
		// }
	} // END __destruct



	/**
	 * delTmpFiles
	 *
	 * @return	void
	 */
	public function delTmpFiles( )
	{ // BEGIN delTmpFiles
		foreach ($this->tmpFiles as $f)
		{
			@unlink($f);
		}
	} // END delTmpFiles


	/**
	 * cacheDatas
	 *
	 * @return	void
	 */
	public function cacheDatas( )
	{ // BEGIN cacheDatas
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('cache last datas.');
		foreach ($this->lastCacheDatas as $v)
		{
			if (is_array($v[0]))
				call_user_func_array(array($this, 'setMulti'), $v);
			else
				call_user_func_array(array($this, 'set'), $v);
		}
	} // END cacheDatas




	/**
	 * setLast
	 *
	 * @return	void
	 */
	public function setLast( $n )
	{ // BEGIN setLast
		$this->lastCacheDatas[]=func_get_args();
	} // END setLast


	/**
	 * getDB
	 *
	 * @return	void
	 */
	public function getDB( $type=0 )
	{ // BEGIN getDB
		if ($type==2) {
			return $this->CDB2;
		} else if ($type==1) {
			return $this->CDB;
		} else {
			return $this->DB;
		}
	} // END getDB


	/**
	 * dumpError
	 *
	 * @return	void
	 */
	public function dumpError( $str, $e = 'error' )
	{ // BEGIN dumpError
		header('HTTP/1.0 500 '.$e);
		$this->LOG->error($str);
		exit;
	} // END dumpError


	/**
	 * set
	 *
	 * @return	void
	 */
	public function set( $n, $v, $e=null, $log=true, $type=0 )
	{ // BEGIN set
		$C =& $this->cnf;
		if (is_null($e))
			$e = $C['siteCnf']['et'];
		
		$db = $this->getDB($type);

		$r = $db->set($n, $v, $e);
		$msg = $db->getResultMessage();

		if ($msg != 'Success') {
			return $this->dumpError("[$msg]set cache value: ".$n." ERROR");
		}

		if ($log)
			$this->LOG->trace("[$msg]set cache value: ".$n." (expiry: $e) -> ".print_r($v,true));
		else
		{
			$this->LOG->trace("[$msg]set cache value: ".$n." (expiry: $e), (length: ".strlen(print_r($v,true)).")");
		}

		return $r;
	} // END set

	/**
	 * setMulti
	 *
	 * @return	void
	 */
	public function setMulti( $n, $e=null, $log=true, $type=0 )
	{ // BEGIN setMulti
		$C =& $this->cnf;
		if (is_null($e))
			$e = $C['siteCnf']['et'];
		
		$db = $this->getDB($type);

		$r = $db->setMulti($n, $e);
		$msg = $db->getResultMessage();


		if ($msg != 'Success') {
			return $this->dumpError("[$msg]set multi cache value: ERROR");
		}

		if ($log)
		{
			$this->LOG->trace("[$msg]set multi cache value (expiry: $e): ".print_r($n,true));
		}
		else
		{
			// $s = '';
			// foreach ($n as $k => $v)
			// {
			// 	$s.="\n$k (length: ".count($v).")";
			// }
			// $this->LOG->trace("[$msg]set multi cache value (expiry: $e): ".$s);
			$this->LOG->trace("[$msg]set multi cache value (expiry: $e): length: ".count($n));
		}

		return $r;
	} // END setMulti

	/**
	 * get
	 *
	 * @return	void
	 */
	public function get( $n, $type=0 )
	{ // BEGIN get
		$db = $this->getDB($type);
		return $db->get($n);
	} // END get

	/**
	 * getMulti
	 *
	 * @return	void
	 */
	public function getMulti( $n, $type=0 )
	{ // BEGIN getMulti
		$db = $this->getDB($type);
		return $db->getMulti($n);
	} // END getMulti


	/**
	 * error
	 *
	 * @return	void
	 */
	public function error( $str, $code=500 )
	{ // BEGIN error
		$u = empty(PC::$RI['bak_url'])?'':PC::$RI['bak_url'];
		header('HTTP/1.0 '.$code.' error');
		
		$C =& $this->cnf;
		$L = $this->LOG;

		if (!empty($C['displaylog'])) {
			$L->CNF['display'] = true;
		}

		$C['debug'] = true;
		if (!empty($C['vid'])) {
			$this->set($C['vid'].'_main', 0, 864000);
			if (!empty($C['id'])) {
				$this->set($C['id'].'_main', 0, 864000);
			}
		}


		if (empty($u))
		{
			$this->LOG->error($str);
		}
		else
		{
			$this->LOG->trace("[ERROR] $str \nredirect to bak url: $u");
			header("Location: $u");
		}
		exit;
	} // END error



	/**
	 * setLock
	 *
	 * @return	void
	 */
	public function setLock( $lockKey, $v, $e=null )
	{ // BEGIN setLock
		$this->locks[$lockKey] = $v;
		$e = is_null($e) ? $this->cnf['lock_timeout'] : $e ;
		$this->set($lockKey, $v, $e);
	} // END setLock


	/**
	 * unsetLocks
	 *
	 * @return	void
	 */
	public function unsetLocks( )
	{ // BEGIN unsetLocks
		foreach ($this->locks as $k => $v)
		{
			if (!empty($v))
				$this->set($k, 0, 1);
		}
	} // END unsetLocks



	/**
	 * waitingLock
	 *
	 * @return	void
	 */
	public function waitingLock( $lockKey )
	{ // BEGIN waitingLock
		$L = $this->LOG;
		$t = 0;
		while (true)
		{
			if (connection_status() != CONNECTION_NORMAL) {
				$L->trace('user disconnection.');
				exit;
			}

			if ($t > 20)
				break;

			$lock = $this->get($lockKey);
			if (empty($lock))
			{
				return true;
			}

			$L->trace($lockKey.' waiting...');
			usleep(500000);
			$t++;
		}

		return false;
	} // END waitingLock


	/**
	 * isLock
	 *
	 * @return	void
	 */
	public function isLock( $lockKey )
	{ // BEGIN isLock
		$lock = $this->get($lockKey);
		$lock = empty($lock) ? false : true ;
		return $lock;
	} // END isLock









	/**
	 * checkvideomux
	 *
	 * @return	void
	 */
	public function checkvideomux( $u )
	{ // BEGIN checkvideomux
		$C =& $this->cnf;
		$L = $this->LOG;

		
		//video_demux: string filename, string ua, string otherHeaader, int limit
		$r = video_demux($u, 'gnome-vfs/2.24.2 Firefox/3.5.2', '', 3);
		$L->trace('video demux: '.$r);

		if ( empty($r) || strpos($r, 'mux') === false ) {
			return false;
		}

		foreach ($C['muxtosite'] as $mux => $site) {
			if ( strpos($r, $mux) !==false ) {
				return $site;
			}
		}

		return false;
	} // END checkvideomux


	/**
	 * prepareByUrl
	 *
	 * @return	void
	 */
	public function prepareByUrl( )
	{ // BEGIN prepareByUrl
		// import parse script dir
		$L = $this->LOG;
		$C =& $this->cnf;

		$u = $C['url'];


		if (empty($C['forceSite']) && !empty($C['checkmux'])) {
			$C['forceSite'] = $this->checkvideomux($u) ? : false ;
		}


		foreach ($C['site_cnfs'] as $k => $sites)
		{

			if ( empty($sites[0]) ) {
				$sites = array($sites);
			}

			$force = ($C['forceSite'] == $k) ? : false;
			foreach ($sites as $i=>$site) {
				$isMatch = $force ? (preg_match($site['pattern'], $u) || count($sites)==$i+1) : preg_match($site['pattern'], $u) ;
				if ( $isMatch  ) {
					$C['siteCnf'] = $site + $C['site_cnf_default'];
					$C['site'] = $k;
					break 2;
				}
			}

		}

		if (empty($C['site']))
		{
			$this->error("nonsupport url.", 403);
		}

		if (empty($C['vid']))
		{
			$fixs = md5(http_build_query($C['params']));
			$C['vid'] = $C['vid_prefix'].substr(md5($u), 0, 22).substr($fixs, -10);

		}
		//$L->trace('quality: '.$C['quality']);
		$C['urlKey'] = md5($u);
		// check ad enable
		if ('ad' == $C['ptype']) {
			$e = $this->get($C['urlKey'].'_ad_disable');
			if (!empty($e)) {
				$L->trace('ad disabled.');
				exit;
			}
		}


		$this->set($C['vid'].'_url', $u, 2505600, true, 1);
		//$this->set($C['vid'].'_params', $C['params'], 2505600, true, 1);

		// show site configs
		//$L->trace('site ['.$C['site'].'] config: '. print_r($C['siteCnf'], true));
		$L->trace('site ['.$C['site'].']');
	} // END prepareByUrl










	/**
	 * genM3u8String
	 *
	 * @return	void
	 */
	public function genM3u8String( $ik=null )
	{ // BEGIN genM3u8String
		$L = $this->LOG;
		$C =& $this->cnf;

		$vid = $C['vid'];
		$id = $C['id'];

		$isGradual = (!is_null($ik) && is_numeric($ik));

		if (!$isGradual)
		{
			$s = $C['infos'];
		}
		else if($isGradual && !empty($C['infos'][$ik]))
		{
			$s = array();
			$s[$ik] = $C['infos'][$ik];
		}
		else
		{
			$this->error("gen m3u8 error, infos[$ik] empty.");
		}


		$m3u8="";
		$targetDuration=0;
		$ts_length_max = $C['m3u8_ts_length']*1000;
		$ts_length = 3000;

		$tsUrlTpl = $C['ts_location'];
		//$tsKeys = array('{vid}','{idx}','{start}','{length}','{vcspeed}');
		$tsKeys = array('{vid}','{id}','{idx}','{tsname}','{vcspeed}','{offline}','{bitrate}','{duration}');

		$tsmode = empty($C['tsmode']) ? 'pos' : $C['tsmode'];
		$dbArr = array();

		//
		$vcspeed = 0;
		$lastSDur = 0;

		// gradual m3u8 max item
		$maxItem = 3;

		$num=0;

		ksort($s);
		$m3u8_data=array();

		// foreach ($poses as $i => $pos)
		foreach ($s as $i => $info)
		{
			if ($isGradual && $i!=$ik)
				continue;

			$idxs = $info['idx'];
			$idxs_length = count($info['idx'][0]);
			$aidxs = $info['aidx'];
			
			// unset the last empty idx
			if ($idxs_length>1)
			{
				$last_num=1;
				while( $last_num<$idxs_length && empty($idxs[1][$idxs_length-$last_num]) )
				{
					$last_num++;
				}

				if (!empty($aidxs[1][0]))
				{
					while( $last_num<$idxs_length && (empty($aidxs[1][$idxs_length-$last_num]) || $aidxs[1][$idxs_length-$last_num]>$idxs[1][$idxs_length-1]) )
					{
						$last_num++;
					}
				}

				$idxs_length-=$last_num;
				//unset($idxs[0][$idxs_length]);
				$idxs[0] = array_slice($idxs[0], 0, $idxs_length);
			}


			

			$lastTime=-1;
			$lastIdx=0;
			$alastIdx=0;
			$lastii=0;
			$lastPip='merge';

			// add EXT-X-DISCONTINUITY
			//$m3u8.="#EXT-X-DISCONTINUITY\n";

			//foreach ($idxs as $time => $idx)
			for($ii=0; $ii<$idxs_length; $ii++)
			{
				$time = $idxs[0][$ii];
				$atime = $aidxs[0][$ii];
				$idx = $idxs[1][$ii];
				$aidx = $aidxs[1][$ii];

				// first key frame
				if ( $lastTime == -1 )
				{
					$lastTime = $time;
					$lastIdx = $idx;
					$alastIdx = $aidx;
					$lastii = $ii;

					// pipeline type check
					if ($aidxs[1][0] > 0)
					{
						$iii = 0;
						while($iii<$idxs_length)
						{
							if ($aidxs[1][$iii]>=$idxs[1][$iii])
							{
								$lastPip = $aidxs[0][$iii]-$idxs[0][$iii] > 1000 ? 'branch' : 'merge' ;
								break;
							}
							$iii++;
						}
					}
					
				}

				// 
				$dur = $time-$lastTime;
				
				if ( $dur >= $ts_length )
				{

					if ($isGradual)
					{
						if ($maxItem <= 0)
							break;
						$maxItem--;
					}
					
					// generate small fragments first: 3,4,5,6,7,8,9,10,10...
					$ts_length += 1000;
					if ($ts_length > $ts_length_max) {
						$ts_length = $ts_length_max;
					}


					//$sdur = (int)round($dur/1000);
					$sdur = round($dur/1000, 3);

					if ($vcspeed==0)
						$vcspeed=$sdur;
					else
						$vcspeed=max(2, min($sdur, 2*($sdur-$lastSDur)+2));

					$lastSDur = $sdur;

					// set m3u8 data
					$m3u8_data[]=array(
							'i' => $i,
							'ii' => $ii,
							'sii' => $lastii,
							'duration' => $sdur,
							'vcspeed' => $vcspeed,
							'pos' => array($lastIdx, $idx),
							'apos' => array($alastIdx, $aidx),
							'time' => array($lastTime, $time),
							'pltype' => $lastPip,
						);

					$lastTime=$time;
					$lastIdx=$idx;
					$alastIdx=$aidx;
					$lastii = $ii;

					// pipeline type check
					if ($aidxs[1][0] > 0)
					{
						$iii = 0;
						while($iii<$idxs_length)
						{
							if ($aidxs[1][$iii]>=$idxs[1][$iii])
							{
								$lastPip = $aidxs[0][$iii]-$idxs[0][$iii] > 1000 ? 'branch' : 'merge' ;
								break;
							}
							$iii++;
						}
					}

				}
			}

			if ( !$isGradual || ($isGradual && $maxItem>0) )
			{

				//$sdur = (int)round($info['duration']-($lastTime/1000));
				//$sdur = (int)round($info['duration']- ( ($lastTime-$idxs[0][0])/1000 ) );
				$sdur = round( $info['duration']- ( ($lastTime-$idxs[0][0])/1000 ), 3 );
				//$sdur = floor( $info['duration'] - ( ($lastTime-$idxs[0][0])/1000 ) );
				
				
				$vcspeed=max(2, min($sdur, 2*($sdur-$lastSDur)));
			
				$m3u8_data[]=array(
						'i' => $i,
						'ii' => -1,
						'sii' => $lastii,
						'duration' => $sdur,
						'vcspeed' => $vcspeed,
						'pos' => array($lastIdx, 0),
						'apos' => array($alastIdx, 0),
						'time' => array($lastTime, 0),
						'pltype' => $lastPip,
					);

			}

		}

		// generate m3u8 string
		$last_i=-1;
		foreach ($m3u8_data as $n => $md)
		{
			if (empty($m3u8_data[$n]))
				continue;

			if ($last_i!=$md['i'])
			{
				$m3u8.="#EXT-X-DISCONTINUITY\n";
				$last_i=$md['i'];
				$sii=0;
				$eii=0;
				$soii=0;
				$eoii=0;
			}

			if (!empty($m3u8_data[$n+1]) 
				&& $m3u8_data[$n+1]['duration']<5
				&& $m3u8_data[$n+1]['pos'][1]==0)
			{
				$md['pos'][1]=$md['apos'][1]=$md['time'][1]=0;
				$md['duration']+=$m3u8_data[$n+1]['duration'];
				$m3u8_data[$n+1]=false;
			}



			if (empty($md['apos'][0]))
			{
				$so = $md['pos'][0];
				$eo = $md['pos'][1];

				// flv
				//$dbArr[$id.$md['i'].$n.'_pos'] =  '0_0,0_0,'.$so.'_'.$eo.','.$md['time'][0].'_'.$md['time'][1];

				$dbArr[$id.$md['i'].$n.'_pos'] = '0_0,0_0,'.$so.'_'.$eo.','.$md['time'][0].'_'.$md['time'][1].','.$md['pltype'];
			}
			else
			{
				$offset=1;
				while ( $md['pos'][0] > $md['apos'][0] && $md['ii']-$offset+1 > 0 ) {
					$md['pos'][0] = $s[$md['i']]['idx'][1][$md['ii']-$offset];
					$offset++;
				}
				$so = $md['pos'][0];


				$offset=1;
				$idxs_length = count($s[$md['i']]['idx'][1]);
				while ( $md['pos'][1] < $md['apos'][1] && $md['ii']+$offset-1 < $idxs_length ) {
					$md['pos'][1] = $s[$md['i']]['idx'][1][$md['ii']+$offset];
					$offset++;
				}
				$eo = $md['pos'][1];

				// old error
				//$so = min($md['pos'][0], $md['apos'][0]);
				//$eo = max($md['pos'][1], $md['apos'][1]);

				// mp4
				//$dbArr[$id.$md['i'].$n.'_pos'] =  $md['pos'][0].'_'.$md['pos'][1].','.$md['apos'][0].'_'.$md['apos'][1].',0_0,0_0';

				//$dbArr[$id.$md['i'].$n.'_pos'] = $md['pos'][0].'_'.$md['pos'][1].','.$md['apos'][0].'_'.$md['apos'][1].','.$so.'_'.$eo.','.$md['time'][0].'_'.$md['time'][1].','.$md['pltype'];
				//$dbArr[$id.$md['i'].$n.'_pos'] = $md['pos'][0].'_'.$md['pos'][1].','.$md['apos'][0].'_'.$md['apos'][1].',0_0,0_0,'.$md['pltype'];

				if ( 'merge' === $md['pltype'] ) {
					$dbArr[$id.$md['i'].$n.'_pos'] = '0_0,0_0,'.$so.'_'.$eo.','.$md['time'][0].'_'.$md['time'][1].','.$md['pltype'];
				} else {
					$dbArr[$id.$md['i'].$n.'_pos'] = $md['pos'][0].'_'.$md['pos'][1].','.$md['apos'][0].'_'.$md['apos'][1].',0_0,0_0,'.$md['pltype'];
				}

				
			}

			//$dbArr[$id.$md['i'].$n.'_pos'] = $md['pos'][0].'_'.$md['pos'][1].','.$md['apos'][0].'_'.$md['apos'][1];
			//$dbArr[$id.$md['i'].$n.'_pos'] = $md['pos'][0].'_'.$md['pos'][1].','.$md['apos'][0].'_'.$md['apos'][1].','.$so.'_'.$eo.','.$md['time'][0].'_'.$md['time'][1];

			//
			//$dbArr[$id.$md['i'].$n.'_pos'] = '0_0,0_0,'.$so.'_'.$eo.','.$md['time'][0].'_'.$md['time'][1].','.$md['pltype'];

			$tsUrl = str_replace($tsKeys, array($vid, $id, $md['i'], $n, $md['vcspeed'], $C['offline'],$C['bitrate'], $md['duration']) , $tsUrlTpl);
			if ($md['duration']>$targetDuration)
				$targetDuration = $md['duration'];
			$m3u8.="#EXTINF:".$md['duration'].",\n".$tsUrl."\n";
		}

		// 
		//$m3u8="#EXTM3U\n#EXT-X-TARGETDURATION:".$targetDuration."\n#EXT-X-MEDIA-SEQUENCE:0\n".$m3u8;
		$m3u8="#EXTM3U\n#EXT-X-TARGETDURATION:".ceil($targetDuration)."\n#EXT-X-VERSION:3\n#EXT-X-MEDIA-SEQUENCE:0\n".$m3u8;

		//if ($tsmode=='db')
		$this->setMulti($dbArr, $C['siteCnf']['et_ts_pos'], false);

		if (!$isGradual)
			$m3u8.="#EXT-X-ENDLIST";

		return $m3u8."\n";
	} // END genM3u8String


	/**
	 * genDTM3u8String
	 *
	 * @return	void
	 */
	public function genDTM3u8String( $argu=array(), $rargu=array() )
	{ // BEGIN genDTM3u8String
		$L = $this->LOG;
		$C =& $this->cnf;

		$m3u8 = "";
		foreach ($C['srcs'] as $i => $src)
		{
			$m3u8 .= "#EXTINF:0,\n$i/0.ts?vs=12&ca=".$C['offline']."\n";
		}
		$m3u8 = "#EXTM3U\n#EXT-X-TARGETDURATION:0\n#EXT-X-MEDIA-SEQUENCE:0\n$m3u8#EXT-X-ENDLIST\n";
		return $m3u8;
	} // END genDTM3u8String



	/**
	 * genM3u8StringByM3u8
	 *
	 * @return	void
	 */
	public function genM3u8StringByM3u8( $argu=array(), $rargu=array() )
	{ // BEGIN genM3u8StringByM3u8
		$L = $this->LOG;
		$C =& $this->cnf;

		global $tsUrl, $_srcsi;

		$L->trace('generate m3u8 string by m3u8 content.');

		//$tsKeys = array('{vid}','{id}','{idx}','{tsname}','{vcspeed}','{offline}','{bitrate}');
		$tsKeys = array('{vid}','{id}','{idx}','{vcspeed}','{offline}','{bitrate}');
		$tsUrl = str_replace($tsKeys, array($C['vid'], $C['id'], 0, 12, $C['offline'], $C['bitrate']) , $C['ts_location']);

		$_srcsi = 0;

		$s = preg_replace_callback('/(\#EXTINF\:[\d\.]+[^\s]*\s+)([^\s]+)/i', function ($m) {
			global $tsUrl, $_srcsi;
			return $m[1].str_replace('{tsname}', $_srcsi++, $tsUrl);
		}, $C['m3u8src_content']);

		$this->set($C['id'].'_ts_location', $tsUrl);

		if ( strpos($C['m3u8src_content'], '#EXT-X-ENDLIST') === false ) {
			$C['siteCnf']['et_m3u8'] = 4;
		}
		
		return $s;
	} // END genM3u8StringByM3u8



	/**
	 * updateM3u8
	 *
	 * @return	void
	 */
	public function updateM3u8( $idx=null )
	{ // BEGIN updateM3u8
		$L = $this->LOG;
		$C =& $this->cnf;

		$vid = $C['vid'];
		$id = $C['id'];


		$sk = $id;
		$isGradual = (!is_null($idx) && is_numeric($idx));
		if ($isGradual)
			$sk.=$idx;


		if (empty($C['forceUpdateM3u8']))
		{
			$s = $this->get($sk.'_m3u8');
		}

		$isCached = !empty($s);

		if ($isCached && empty($C['refreshExpiredTime']))
		{
			$m3u8 = $s;
			$L->trace("get cached m3u8:\n".substr($s, 0, 200).'...');
		}
		else
		{
			// get src urls
			$L->trace('update m3u8.');

			if ($C['dt']) {
				$m3u8 = $this->genDTM3u8String();
			} else if ( $C['ism3u8'] ) {
				$m3u8 = $this->genM3u8StringByM3u8();
			} else {
				$m3u8 = $this->genM3u8String($idx);
			}

			if ($isGradual)
			{
				$L->trace("generate gradual m3u8:\n$m3u8");
				$this->set($sk.'_m3u8', $m3u8, $C['siteCnf']['et_gradual_m3u8'], false);
			}
			else
			{
				$L->trace("generate m3u8:\n".substr($m3u8,0,200).'...');
				$this->set($sk.'_m3u8', $m3u8, $C['siteCnf']['et_m3u8'], false);
			}

		}

		if (!empty($C['redirectM3u8']))
		{
			if (empty(PC::$RI['m3u8_location']))
			{
				$ipmode=0;
				if (isset($_GET['ipmode']))
				{
					$ipmode=(int)$_GET['ipmode'];
				}
				else if ( !empty($_SERVER['HTTP_USER_AGENT']) && preg_match('/ip=(\d+)/i', $_SERVER['HTTP_USER_AGENT'], $m))
				{

					$ipmode=(int)$m[1];
				}
				else
				{
					$_s= empty($_SERVER["HTTP_X_REAL_IP"]) ? '' : substr($_SERVER["HTTP_X_REAL_IP"], 0, 3);
					// xm 172.25.3.199 only
					if ('172' == $_s || '119' == $_s)
						$ipmode = 1;
				}
				if ($ipmode >= count($C['m3u8_location'][$C['cdn']]))
					$ipmode=0;
				$m3u8_location = $C['m3u8_location'][$C['cdn']][$ipmode];
			}
			else
			{
				$m3u8_location = PC::$RI['m3u8_location'];
			}

			$rUrl = str_replace( array('{vid}','|id|','|flags|'), array($vid,$C['id'],$C['flags']), $m3u8_location);
			$L->trace('redirect to: '.$rUrl);
			if ($isGradual)
			{
				Net::redirectAndContinue($rUrl);
			}
			else
			{
				Net::redirectAndContinue($rUrl);
			}
			$C['redirectM3u8'] = false;
		}
	} // END updateM3u8



	/**
	 * cacheEachSrcUrls
	 *
	 * @return	void
	 */
	public function cacheEachSrcUrls( $srcs, $e=null, $key = '_src')
	{ // BEGIN cacheEachSrcUrls
		$C =& $this->cnf;
		$L = $this->LOG;
		if (is_null($e))
			$e = $C['siteCnf']['et_srcs'];
		$d = array();
		foreach ($srcs as $i => $src)
		{
			//$this->set($C['id'].$i.$key, $src, $e, false);
			$d[$C['id'].$i.$key] = $src;
		}

		$L->trace('cache '.$key);
		$this->set($C['id'].$key.'_count', count($srcs), $e);
		$this->setMulti($d, $e, false);

	} // END cacheEachSrcUrls


	/**
	 * selectSrcs
	 *
	 * @return	void
	 */
	public function selectSrcs(  )
	{ // BEGIN selectSrcs
		$C =& $this->cnf;
		

		if ( 'fetch' == $C['srctype'] && empty($C['siteCnf']['fakeinfos']) ) {
			
			$C['srcs'] = $C['fetchSrcs'];
			$C['_srcs'] = $C['_fetchSrcs'];
			$C['siteCnf']['et_srcs'] = $C['siteCnf']['et_fetch'];

			if (!empty($C['ism3u8'])) {
				$this->m3u8srcReplaceFetch();
			}	
		} else {
			$C['srcs'] = $C['origSrcs'];
			$C['_srcs'] = $C['_origSrcs'];
		}

	} // END selectSrcs


	/**
	 * updateSrcUrls
	 *
	 * @return	void
	 */
	public function updateSrcUrls( $idx=null )
	{ // BEGIN updateSrcUrls
		$L = $this->LOG;
		$C =& $this->cnf;

		$sk = empty($C['id']) ? $C['vid'] : $C['id'];
		$lockKey = $sk.'_getSrcUrls_LOCK';

		$s = empty($C['cachedSrcs']) ? null: $C['cachedSrcs'];

		// main key exprired, refresh
		//if (!empty($C['id']))
		//{

		$isExists = $this->get($sk.'_main');
		$isParsed = $this->get($sk.'_srcs');
		if ( empty($isExists) && !empty($isParsed) )
			$C['forceUpdateSrcs']=true;
		
		//}
		
		if ( !empty($C['forceUpdateSrcs']) ) {
			$L->trace('forceUpdateSrcs');
		}
		

		// get cached src urls
		if (empty($s))
		if (empty($C['forceUpdateSrcs']))
		{
			if ($this->isLock($lockKey))
				$this->waitingLock($lockKey);
			$s = $this->get($sk.'_srcs');
		}

		$isCached = (!empty($s));

		if ($isCached)
		{
			// $C['total'] = $s['total'];
			
			// $C['origSrcs'] = $s['origSrcs'];
			// $C['fetchSrcs'] = $s['fetchSrcs'];
			// $C['duration'] = $s['duration'];
			// $C['durations'] = empty($s['durations']) ? array() : $s['durations'];
			// $C['id'] = $s['id'];
			$C = $s + $C;

			//$C['srcs'] = $s['srcs'];
			$this->selectSrcs();
			
			// if (empty($C['uid']))
			// {
			// 	//$C['uid'] = uniqid();
			// 	$C['uid'] = $s['uid'];
			// }
				
			// if (empty($C['id']))
			// 	$C['id'] = $C['vid'].'-'.$C['uid'];

			$C['srcs_parse_completed'] = (count($C['srcs']) == $C['total'] && !empty($C['total']));
			
			$L->trace('total :'.$C['total'].', srcs completed: '.count($C['srcs']));

			$C['cachedSrcs'] = $s;
		}

		

		if ($isCached && !is_null($idx) && !empty($C['srcs'][$idx]) )
		{
			$L->trace('get cached src url: '.$C['srcs'][$idx]);
		}
		else if ($isCached && $C['srcs_parse_completed'])
		{
			$L->trace('get cached srcs: '.print_r($s['origSrcs'],true) ); //.print_r($s, true)
		}
		else
		{

			// get src urls
			$L->trace('update src urls.');
			$this->setLock($lockKey, 1);

			$params = empty($C['parsesrc_params']) ? http_build_query($_GET) : $C['parsesrc_params'] ;
			$_force = empty($C['forceUpdateSrcs']) ? 0 : 1 ;
			$ext = ( false === strpos($params, 'u') ) ? "&u=".urlencode($C['url']) : '' ;

			

			$curl = $C['siteCnf']['parsesrc_url']."?".$params.$ext."&return_type=json&force=$_force&hd=".$C['hd']."&_=".uniqid();

			if (!is_null($idx))
				$curl .= "&idx=".$idx;

			$L->trace("touch: $curl");
			$opts = array(
				CURLOPT_TIMEOUT=>15,
				CURLOPT_CONNECTTIMEOUT => 10,
			);
			if (!empty($C['uc'])) {
				$ucookie = $this->get( md5($C['url']).'_cookie' );
				if (!empty($ucookie)) {
					// set cookie
					//$opts[CURLOPT_COOKIE] = $ucookie;

					// set custom header ucookie
					$opts[CURLOPT_HTTPHEADER] = array('ucookie: '.$ucookie);
				}
			}

			$c = Net::curl($curl, $opts);

			if ($c && ($jc=json_decode($c, 1)))
			{
				
				if (false !== $C['seg']) {
					if ( empty($jc['srcs'][$C['seg']]) ) {
						$jc['srcs'] = array(end($jc['srcs']));
						$jc['videoinfo'] = array(end($jc['videoinfo']));
					} else {
						$jc['srcs'] = array($jc['srcs'][$C['seg']]);
						if (!empty($jc['videoinfo'])) {
							$jc['videoinfo'] = array($jc['videoinfo'][$C['seg']]);
						}
					}
					$jc['total'] = 1;
				}

				if ( !empty($C['upl']) ) {
					if (!empty($C['su'])) {
						$su = explode('#', $C['su']);
						$jc['srcs'] = array_merge($su, $jc['srcs']);
						$jc['total'] += count($su);
					}

					if (!empty($C['eu'])) {
						$eu = explode('#', $C['eu']);
						$jc['srcs'] = array_merge($jc['srcs'], $eu);
						$jc['total'] += count($eu);
					}

				}
				
				$C['total'] = $jc['total'];
				$C['origSrcs'] = $jc['srcs'];

				$C['duration'] = empty($jc['duration']) ? 0 : $jc['duration'];


				$C['durations'] = empty($jc['durations']) ? array() : $jc['durations'];
				$C['size'] = empty($jc['size']) ? 0 : $jc['size'];
				$C['hd'] = empty($jc['hd']) ? 0 : $jc['hd'];
				$C['videoinfo'] = empty($jc['videoinfo']) ? array( array() ) : $jc['videoinfo'];
				
				$C['bitrate'] = empty($C['videoinfo'][0]['br']) ? 0 : (int)$C['videoinfo'][0]['br'];
				$C['bitrate'] *= 1024;
				$C['isbak'] = empty($jc['isbak']) ? false : true;
				


				if ( empty($C['duration']) && $C['total'] == 1 && !empty($C['videoinfo'][0]) && !empty($C['videoinfo'][0]['duration']) ) {
					$C['duration'] = $C['videoinfo'][0]['duration'];
				}





				$C['ism3u8'] = empty($jc['ism3u8']) ? false : true;
				if ($C['ism3u8']) {
					//$C['idx'] = $jc['idx'];
					//$C['_origSrcs'] = $jc['_srcs'];
					$this->parseM3u8Src($C['origSrcs'][0]);
				}
				


				if ( $C['isbak'] ) {
					$C['siteCnf']['et_srcs'] = $C['siteCnf']['et_fetch'] = 90;
					$C['siteCnf']['et_main'] = 90;
					$L->trace($jc['srcerror']);
					$this->set($C['urlKey'].'_ad_disable', 1, 2505600);
				}

				// if (empty($C['uid']))
				// 	$C['uid'] = $jc['id'];
				if (empty($C['id'])) {
					if ( !empty($C['duration']) && !empty($C['size']) ) {
						$C['id'] = $C['site'].'-'.$C['vid_prefix'].$C['hd'].md5($C['duration'].'_'.$C['size']);
					} else if ( !empty($C['duration']) && !empty($C['videoinfo'][0]) ) {
						$C['id'] = $C['site'].'-'.$C['vid_prefix'].$C['hd'].md5($C['duration'].'_'.serialize($C['videoinfo'][0]));
					} else {
						$C['id'] = $C['site'].'-'.$C['vid_prefix'].$C['hd'].md5(implode(',', $C['origSrcs']));
					}
				}
					

				$this->set($C['id'].'_parsesrc_params', $params, 2505600, true, 1);

				if (empty($C['mainCached']))
				{
					$this->set($sk.'_main', 1, $C['siteCnf']['et_main']);
					if ($sk!=$C['id'])
						$this->set($C['id'].'_main', 1, $C['siteCnf']['et_main']);

					$C['refreshExpiredTime']=true;
					$C['mainCached']=true;
				}

			}
			else
			{
				$this->error("srcs get error: \n".$c, 404);
			}


			$this->set($C['id'].'_vid', $C['vid'], 2505600, true, 1);
			$C['params']['hd'] = $C['hd'];
			$this->set($C['vid'].'_params', $C['params'], 2505600, true, 1);

			// check is parse completed
			$C['srcs_parse_completed'] = ($C['total']==count($C['origSrcs']) && !empty($C['total']));
			
			// gen fetch srcs
			$C['fetchSrcs'] = array();
			foreach ($C['origSrcs'] as $i => $src) {
				$psrc = substr($src, 7);
				$C['fetchSrcs'][$i] = str_replace( array('|id|', '|idx|', '|src|', '|psrc|'), array($C['id'], $i, $src, $psrc), $C['fetch_location'] );
			}


			$C['_fetchSrcs'] = array();

			if ( !empty($C['_origSrcs']) ) {
				foreach ($C['_origSrcs'] as $i => $src) {
					$psrc = substr($src, 7);
					$C['_fetchSrcs'][$i] = str_replace( array('|id|', '|idx|', '|src|', '|psrc|'), array($C['id'], $i, $src, $psrc), $C['_fetch_location'] );
				}
			} else {
				$C['_origSrcs'] = array();
			}
			


			$this->selectSrcs();
			$this->cacheEachSrcUrls($C['srcs']);
			$this->cacheEachSrcUrls($C['origSrcs'], null, '_origsrc');

			$this->cacheEachSrcUrls($C['_srcs'], null, '__src');
			$this->cacheEachSrcUrls($C['_origSrcs'], null, '__origsrc');
		
			$this->cacheSrcs();
			$this->set($C['id'].'_bitrate', $C['bitrate']);

			$this->setLock($lockKey, 0);
		}

		return $C['srcs'];
	} // END updateSrcUrls





	/**
	 * cacheSrcs
	 *
	 * @return	void
	 */
	public function cacheSrcs( )
	{ // BEGIN cacheSrcs
		$L = $this->LOG;
		$C =& $this->cnf;

		$s = array(
			'total' => $C['total'],
			'fetchSrcs' => $C['fetchSrcs'],
			'origSrcs' => $C['origSrcs'],
			'id' => $C['id'],
			'duration' => $C['duration'],
			'size' => $C['size'],
			'durations' => $C['durations'],
			'videoinfo' => $C['videoinfo'],
			'bitrate' => $C['bitrate'],
			'isbak' => $C['isbak'],
			'm3u8src_content' => empty($C['m3u8src_content']) ? '' : $C['m3u8src_content'],
			'_fetchSrcs' => $C['_fetchSrcs'],
			'_origSrcs' => $C['_origSrcs'],
			'ism3u8' => $C['ism3u8'],
			'idx' => empty($C['idx']) ? array() : $C['idx'],
		);
		$C['cachedSrcs'] = $s;

		$k = array();
		$k[$C['vid'].'_srcs'] = $s;
		$k[$C['id'].'_srcs'] = $s;

		$L->trace('et_srcs: '.$C['siteCnf']['et_srcs']);
		$this->setMulti($k, $C['siteCnf']['et_srcs'], false);

	} // END cacheSrcs








	/**
	 * videoidxparse
	 *
	 * @return	array
	 */
	public function videoidxparse( $id, $src )
	{ // BEGIN videoidxparse

		// user agent
		//$ua = empty($_SERVER["HTTP_USER_AGENT"]) ? 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11' : $_SERVER["HTTP_USER_AGENT"];
		$ua = 'gnome-vfs/2.24.2 Firefox/3.5.2';

		$L = $this->LOG;
		$C =& $this->cnf;
		$r = array();

		$f=$C['vpos_path'].uniqid();
		$r['file'] = $f;

		//$cmd = $this->__DIR__."videoidxparse10 -f '$src' -u '$ua' -i '$id' -a '{$C['db_url']}' -l {$C['parse_pos_each_timeout']} -r '{$id}_src' > $f 2>&1 & echo $!";

		$k = array('{src}','{ua}','{id}','{db}','{db_server}','{timeout}','{file}','{vidxp}');
		$v = array($src,$ua,$id,$C['db_url'],$C['db_server'],$C['parse_pos_each_timeout'],$f,$C['siteCnf']['et_vidxp']);

		if ( !empty($C['siteCnf']['fakeinfos']) ) {
			$cmd = "echo 1 >$f 2>&1 & echo $!";
		} else if (!empty($C['siteCnf']['isTS'])) {
			$s = parse_url($src);
			$s['port'] = empty($s['port']) ? '' : ':'.$s['port'];
			$s['query'] = empty($s['query']) ? '' : '?'.$s['query'];
			$p = str_replace($k, $v, " -u '{ua}' -i '{id}' -a '{db_server}' -l {timeout} -r '{id}_src' ");
			$cmdUrl = 'http://'.$s['host'].$s['port'].'/app/tsidxparse/?f='.urlencode($s['path']).'&p='.urlencode($p);

			$cmd = "curl -L '$cmdUrl' >$f 2>&1 & echo $!";

		} else {
			$cmd = str_replace($k, $v, $C['siteCnf']['videoidxparse']);
		}


		$re=shell_exec($cmd);

		$this->tmpFiles[]=$f;

		// get process id
		if (preg_match('/(?:^|\s+)(\d+)(?:\s+|$)/i', $re, $m))
		{
			$r['pid'] = $m[1];
			$L->trace("[{$m[1]}] $cmd");
		}
		else
		{
			$this->error("exec: $cmd\nerror: $re");
			exit;
		}

		return $r;
	} // END videoidxparse



	/**
	 * parseInfos
	 *
	 * @return	array
	 */
	public function parseInfos( $f, $i = 0 )
	{ // BEGIN parseInfos
		$C = & $this->cnf;
		$r = array();

		if ( !empty($C['siteCnf']['fakeinfos']) ) {
			$C['profile'] = 'PT';
			$C['siteCnf']['fakeinfos']['uri'] = $C['origSrcs'][0];
			return $C['siteCnf']['fakeinfos'];
		}

		if ( !empty($C['siteCnf']['isTS']) ) {
			$fc = $this->get($C['id'].$i.'_tsidxparse_result');
			if (empty($fc)) {
				return "file: $f data empty: ".$C['id'].$i.'_tsidxparse_result';
			}
		} else if ( !file_exists($f) || !($fc = file_get_contents($f)) ) {
			return "file: $f empty.";
		}

		$rc = "\nRESULT CONTENTS: ".$fc;

		// check http error
		if ( !empty($C['checkhttperror']) && preg_match('/HTTP\s+error.*/six', $fc, $m) ) {
			$this->error("parse video[$i] error: ".$m[0]);
			return $m[0];
		}


		// get duration, bitrate
		if (!empty($C['ism3u8'])) {
			if ( preg_match('/bitrate\s*\:\s*\[(.*?)\]/six', $fc, $m) ) {
				$r['duration'] = $C['duration'];
				$r['br'] = $m[1]*1024;
			} else {
				return 'bitrate parse error.'.$rc;
			}
		} else if ( preg_match('/Duration\s*:\s*\[\s*(\d+):(\d+):([\d\.]+)\s*\]\s*bitrate\s*\:\s*\[(.*?)\]/six', $fc, $dur) )
		{
			$r['duration'] = $dur[1]*3600+$dur[2]*60+$dur[3];
			$r['br'] = $dur[4]*1024;
		}
		else
		{
			//$L->trace("[WARNING] video[$i] duration,bitrate parse error.");
			return 'duration,bitrate parse error.'.$rc;
		}

		if (preg_match('/mux\:([^\s\,]+)/six', $fc, $m)) {
			$r['mux'] = $m[1];
		} else {
			$r['mux'] = '';
		}


		// fps
		if (preg_match('/([\d\.]+)\s*fps\,/six', $fc, $fps) )
		{
			$r['fps'] = (int)$fps[1];
		}
		else
		{
			$r['fps'] = null;
		}

		// asf_packet_length
		if (preg_match('/asf_packet_length:\[(\d+)\]/six', $fc, $apl) )
		{
			$r['apl'] = (int)$apl[1];
		}
		else
		{
			$r['apl'] = null;
		}

		// get ac,asr
		if ( preg_match('/Audio\s*\:\s*([^\s\,]+)[^\,]*?(?:\[(.*?)\]|)\,\s*(\d+)\sHz\,\s*([^\,]+)(?:\,.*?\,\s*(\d+)\s*kb\/s|)/six', $fc, $mInfo) )
		{
			$r['ac'] = $mInfo[1];
			$r['acodecs'] = $mInfo[2];
			$r['asr'] = $mInfo[3];
			$r['achs'] = $mInfo[4];
			if (!empty($mInfo[5]))
				$r['abr'] = $mInfo[5];
		}
		else
		{
			//$L->trace("[WARNING] video[$i] ac,asr parse error.");
			//return 'ac,asr parse error.'.$rc;
			$r['ac'] = 'NONE';
			$r['asr'] = $r['abr'] = null;
		}

		// get vc
		if ( preg_match('/Video\s*:\s*([^\s\,]+)[^\,]*?(?:\[(.*?)\]|)\,.*?(\d+)x(\d+)\s*[\[\,].*?(?:(\d+)\s*kb\/s|)/six', $fc, $vInfo) )
		{
			$r['vc'] = $vInfo[1];
			$r['vcodecs'] = $vInfo[2];
			$r['vw'] = $vInfo[3];
			$r['vh'] = $vInfo[4];

			if (!empty($r['vw']) && !empty($r['vh']))
			{
				// 0.75 < 320*240 < 1.25
				// 320*240/16
				$area = $C['siteCnf']['areaSize']/(16*16);
				$aMax = $area*1.25;

				
				// force %16
				$r['tvw'] = (int)($r['vw']-$r['vw']%16);
				$r['tvh'] = (int)($r['vh']-$r['vh']%16);
				$pp = $r['tvh']/$r['tvw'];

				$lastArea = $aMax - $area;
				$h = 0;
				$w = 0;
				$i = 0;
				while( true ) {
					$i++;
					$t = $i*$pp;
					$dvalue = $i*$t - $area;
					if ($dvalue > $aMax )
						break;

					if ( ceil($t) == $t && ($abs=abs($dvalue)) < $lastArea ) {
						$w = $i;
						$h = $t;
						$lastArea = $abs;
					}
				}


				// old
				if ($w) {
					$r['tvw']=$w*16;
					$r['tvh']=$h*16;
				}
				else
				{
					$r['tvw']=sqrt($C['siteCnf']['areaSize']*$r['vw']/$r['vh']);
					$r['tvh']=$r['tvw']*$r['vh']/$r['vw'];
					$r['tvw']=(int)($r['tvw']-$r['tvw']%16);
					$r['tvh']=(int)($r['tvh']-$r['tvh']%16);
				}
			}
			

			if (!empty($vInfo[5]))
				$r['vbr'] = $vInfo[5];
		}
		else
		{
			$r['vc'] = 'NONE';
			$r['vw'] = $r['vh'] = $r['vbr'] = null;
			if (!empty($C['passaudio'])) {
				$this->error("parse video[$i] error: vc NONE");
				return 'vc parse error.';
			}
		}

		// get idx position
		if (empty($C['ism3u8'])) {
			if ( preg_match_all('/video-pos-time:\[(\d+),(\-?\d+)\].*?audio-pos-time:\[(\d+),(\-?\d+)\]/six', $fc, $pos) )
			{
				//$r['idx'] = array_combine($pos[2], $pos[1]);
				//$r['aidx'] = array_combine($pos[4], $pos[3]);
				$r['idx'] = array($pos[2], $pos[1]);
				$r['aidx'] = array($pos[4], $pos[3]);
				// $L->trace("video[$i] posCount[".count($C['videoInfos'][$i]['idx'])."]");
			}
			else
			{
				//$this->error("video[$i] pid[$pid] key frame position parse error.\nRESULT CONTENTS: ".$fc);
				return "key frame position parse error. CONTENTS:\n".$rc;
			}
		}

		$r['timeoffset'] = 0;

		return $r;
	} // END parseInfos









	/**
	 * updateInfosAction
	 *
	 * @return	void
	 */
	public function updateInfosAction( $idx=null )
	{ // BEGIN updateInfosAction
		$L = $this->LOG;
		$C =& $this->cnf;

		$vid = $C['vid'];
		$id = $C['id'];
		
		// check is m3u8
		$srcs = empty($C['ism3u8']) ? $C['srcs'] : $C['_origSrcs'] ;
		if (!is_null($idx) && !empty($srcs[$idx])) {
			$_srcs = array();
			$_srcs[$idx] = $srcs[$idx];
			$srcs = $_srcs;
		}


		$total = $C['total'];

		if (empty($C['processing']))
			$C['processing'] = array();

		// empty files[0]
		if (empty($C['files']))
			$C['files'] = array();
		if (empty($C['pids']))
			$C['pids'] = array();
		if (empty($C['infos']))
			$C['infos'] = array();

		// exec each src url
		for ($i=0; $i < $total; $i++)
		{
			if (empty($srcs[$i]) || !empty($C['pids'][$i]) || (!empty($C['infos'][$i]) && isset($C['infos'][$i]['duration']) )   )
				continue;

			$src = $srcs[$i];

			// check cached
			$cc = $this->getMulti(array($id.$i.'_infos', $id.$i));
			if (!empty($cc) && !empty($cc[0]) && isset($cc[0]['duration']) && !empty($cc[1]))
			{
				$C['infos'][$i] = $cc[0];
				$L->trace("{$id}{$i}_infos cached.");
				continue;
			}

			// exec videoidxparse
			$vr = $this->videoidxparse($id.$i, $src);
			$C['files'][$i] = $vr['file'];
			$C['pids'][$i] = $vr['pid'];
		}


		// microsecond
		$sleepMicroTime = 200000;
		$sleepTime = $sleepMicroTime/1000000;

		$timeCosted = 0;
		$failedTimes = array();

		while(true)
		{
			for ($i=0; $i <= $total; $i++)
			{
				if (empty($C['pids'][$i]))
					continue;

				if (empty($failedTimes[$i]))
					$failedTimes[$i]=0;


				$f = $C['files'][$i];
				$pid = $C['pids'][$i];

				// check is finished
				clearstatcache();
				if ($pr = file_exists('/proc/'.$pid))
				{
					continue;
				}

				$L->trace("parse index[$i] spend time: $timeCosted s.");

				$r = $this->parseInfos($f, $i);
				if (is_string($r))
				{
					if ($failedTimes[$i]++>0)
					{
						foreach ($C['pids'] as $_pid) {
							@exec('kill -9 '.$_pid);
						}

						$this->error("parse video[$i] error: ".$r);
						$this->setMulti(array(
							$C['id'].'_srcs' => '',
							$C['vid'].'_srcs' => '',
							$C['id'].$i.'_src' => '',
							), 5);
					}	

					$L->trace("[WARNING] (try again) parse video[$i] error: ".$r);
					$vr = $this->videoidxparse($id.$i, $srcs[$i]);
					$C['pids'][$i] = $vr['pid'];
					$C['files'][$i] = $vr['file'];
					continue;
				}


				// parse success
				unset($C['pids'][$i]);
				$r['uri'] = $C['srcs'][$i];
				
				if ($C['ism3u8']) {
					$r['duration'] = $C['duration'];
					$r['m3u8playlist'] = true;
					$r['idx'] = $C['idx'][$i];
					$r['aidx'] = array();
				}
				
				$C['infos'][$i] = $r;

				// add overlay text
				if ( !empty($C['olt']) ) {
					$C['infos'][$i]['olt'] = $C['olt'];
				}

				// cache video infos
				$_infos=$C['infos'][$i];
				if (!empty($_infos['idx'])) {
					$_infos['idx'] = count($_infos['idx']);
					$_infos['aidx'] = count($_infos['aidx']);
				}
				$L->trace($id.$i.'_infos: '.print_r($_infos, true));
				$this->set($id.$i.'_infos', $C['infos'][$i], $C['siteCnf']['et_infos'], false);
			}


			// update gradual m3u8
			if ( !is_null($idx) && !empty($C['infos'][$idx]) && $timeCosted>2 )
			{
				ksort($C['infos']);
				return $C['infos'];
			}


			if (empty($C['pids']))
				break;

			//
			$timeCosted += $sleepTime;

			// check timeout
			if ($timeCosted>$C['parse_pos_timeout'])
			{
				foreach ($C['pids'] as $_pid) {
					@exec('kill -9 '.$_pid);
				}
				$this->error("parse pos timeout, limit: {$C['parse_pos_timeout']}, spend time: $timeCosted s.");
				exit;
			}
			
			usleep($sleepMicroTime);
		}

		ksort($C['infos']);


		// check duration
		if (is_null($idx) && empty($C['duration'])) {
			$C['duration'] = 0;
			foreach ($C['infos'] as $in) {
				if (empty($in['duration'])) {
					continue;
				}
				$C['duration'] += $in['duration'];
			}
			$this->cacheSrcs();
		}

		return $C['infos'];
	} // END updateInfosAction






	/**
	 * cacheEachInfos
	 *
	 * @return	void
	 */
	public function cacheEachInfos( $s )
	{ // BEGIN cacheEachInfos
		$L = $this->LOG;
		$C =& $this->cnf;
		$id = $C['id'];

		ksort($s);

		$L->trace('cache each video infos.');
		$a=array();
		//$timeoffset = 0;

		foreach ($s as $i => $infos)
		{
			//$infos['timeoffset'] = $timeoffset*1000;
			$a[$id.$i.'_infos']=$infos;
			//$timeoffset += $infos['duration'];
		}

		$this->setMulti($a, $C['siteCnf']['et_infos']*3, false);
		$L->trace('cache each infos['.implode(',', array_keys($s)).']');
	} // END cacheEachInfos


	/**
	 * updateInfos
	 *
	 * @return	void
	 */
	public function updateInfos( $idx=null )
	{ // BEGIN updateInfos
		$L = $this->LOG;
		$C =& $this->cnf;

		

		// is completed return
		if ( !empty($C['infos_parse_completed']) && !empty($C['infos']) ) {
			return $C['infos'];
		}

		$L->trace('updateInfos('.$idx.')');

		$vid = $C['vid'];
		$id = $C['id'];

		$sk=is_null($idx)? false : $id.$idx;


		$lockKey = $id.'_getInfos_LOCK';

		$s = empty($C['cachedInfos']) ? null: $C['cachedInfos'];

		if (!empty($C['forceUpdateInfos'])) {
			$L->trace('forceUpdateInfos');
		}

		// get cached video infos
		if (empty($s)) {
			if ( empty($C['forceUpdateInfos']) && empty($C['force']) ) {
				if ($this->isLock($lockKey))
					$this->waitingLock($lockKey);

				$s = $this->get($id.'_infos');
				// if ($sk)
				// 	$ss = $this->get($sk.'_infos');
				// else
				// 	$ss = $this->get($id.'0_infos');
			}
		}

		// check headers[0]
		$s0 = $this->get($id.'0', 2);
		//$ss = $this->getMulti( array($id.'0', $id.'0_infos') );
		$sinfos0 =  $this->get($id.'0_infos');

		//$isCached = (!empty($s) && !empty($ss));
		$isCached = (
					 !empty($s) &&
					 ( !empty($s0) || !empty($s['infos'][0]['m3u8playlist']) ) && 
					 !empty($sinfos0) && 
					 !empty($s['infos'][0]['idx'])
					);
		//$isCached = (!empty($s) && !empty($s['infos'][0]['idx']));
		


		if ($isCached)
		{
			$C['infos'] = $s['infos'];
			$C['infos_parse_completed'] = (count($C['infos']) == $C['total'] && !empty($C['total'])) ? true : false;

			// if not hls live mode, then check every seg's duration is exists
			if ( empty($s['infos'][0]['m3u8playlist']) ) {
				foreach ($C['infos'] as $_info) {
					if ( empty($_info['duration']) ) {
						$C['infos_parse_completed'] = false;
						break;
					}
				}
			}
			

			$C['cachedInfos'] = $s;
			$L->trace('infos cached, infos parse completed: '.$C['infos_parse_completed'].' '.count($C['infos']).'/'.$C['total']);
		} else {
			$L->trace('infos not cached.');
			//$L->trace('s:'.print_r($s, true).'s0:'.print_r($s0, true).'sinfos0:'.print_r($sinfos0, true).'infosidx:'.print_r($s['infos'][0]['idx'], true));
		}

		

		if ( $isCached && !empty($C['infos_parse_completed']) ) {
			$L->trace('get cached infos[all].');
		} else if ( $isCached && $idx !== false && !empty($C['infos'][$idx]) ) {
			$L->trace("get cached infos[$idx]");
		} else {
			// get src urls
			if (is_null($idx)) {
				$L->trace('update all video infos.');
			} else {
				$L->trace('update video['.$idx.'] infos.');
			}
			


			$this->setLock($lockKey, 1);
			$C['refreshExpiredTime']=true;

			if ($sk)
			{
				$this->updateInfosAction($idx);
			}
			else
			{
				$this->updateInfosAction();
			}


			// check is parse completed
			$C['infos_parse_completed'] = ($C['total'] == count($C['infos']) && !empty($C['total']));
			$L->trace('infos parse completed: '.$C['infos_parse_completed'].' '.count($C['infos']).'/'.$C['total']);



			$infos = $C['infos'];
			for ($i=0; $i < $C['total']; $i++) {
				if ( empty($infos[$i]) || empty($infos[$i]['uri']) ) {
					$infos[$i]['uri'] = $C['srcs'][$i];
				}
			}


			if ( !empty($C['infos_parse_completed']) ) {
				// set timeoffset
				$timeoffset = 0;
				foreach ($infos as $_i => $_infos)
				{
					$_infos['timeoffset'] = $timeoffset*1000;
					$timeoffset += $_infos['duration'];
					$infos[$_i] = $_infos;
					
				}
			}

			// set each infos to db
			$this->cacheEachInfos($infos);

			// {id}_infos
			$s = array(
					'total' => $C['total'],
					'infos' => $infos,
					'parsefinished' => empty($C['infos_parse_completed']) ? 0 : 1, // flags, check the parse infos processing IS OR NOT finished
					'id' => $C['id'],
					'duration' => empty($C['duration']) ? 0 : $C['duration'],
				);

			if ( !empty($C['olt']) ) {
				$s['olt'] = $C['olt'];
			}

			//$C['cachedInfos'] = $s;
			$_s = $s;
			$_s['infos'] = $C['infos'];
			$C['cachedInfos'] = $_s;
			
			$L->trace('total duration: '.$C['duration']);
			$this->set($id.'_infos', $s, $C['siteCnf']['et_infos'], false);
			$this->setLock($lockKey, 0);
		}

		if ($isCached && !empty($C['refreshExpiredTime'])) {
			$this->set($id.'_infos', $s, $C['siteCnf']['et_infos'], false);
			$this->cacheEachInfos($s['infos']);
		}
			

		return $C['infos'];
	} // END updateInfos
















	/**
	 * parseM3u8Src
	 *
	 * @return	void
	 */
	public function parseM3u8Src( $u )
	{ // BEGIN parseM3u8Src
		$L = $this->LOG;
		$C =& $this->cnf;

		$o = Net::curlo($u);
		$L->trace('touch: '.$u);

		if (empty($o)) {
			$this->error('content empty.');
			return false;
		}

		$con = $o['body'];
		$url = $o['info']['url'];

		if (strpos($con, 'EXT-X-STREAM-INF') === false) {
			$d = $this->parsePlaylist($url, $con);
		} else {
			$d = $this->parseStream($url, $con);
		}

		$C['origSrcs'] = $d['srcs'];
		$C['_origSrcs'] = $d['_srcs'];
		$C['duration'] = $d['duration'];
		$C['idx'] = $d['idx'];
		$C['m3u8src_content'] = $d['m3u8src_content'];
	} // END parseM3u8Src








	/**
	 * parseStream
	 *
	 * @return	void
	 */
	public function parseStream( $url, $con )
	{ // BEGIN parseStream
		$L = $this->LOG;
		$C =& $this->cnf;

		if ( preg_match_all('/EXT-X-STREAM-INF.*?BANDWIDTH\=(\d+).*\s*([^\s]+)/i', $con, $m) || preg_match_all('/EXT-X-STREAM-INF.*\s*([^\s]+)/i', $con, $m) ) {
			$u = '';
			$lastBW = -1;
			//$L->trace(print_r($m, true));
			foreach ($m[2] as $i => $v) {
				$bw = empty($m[1][$i]) ? 0 : (int)$m[1][$i] ;
				if ($bw > $lastBW) {
					$u = $v;
					$lastBW = $bw;
				}
			}

			// TODO: handle multi br HLS


			// get simgle br m3u8 infos
			$L->trace('get playlist: '.$u);
			$u = $this->formatRelativeUrl($u, $url);

			// set simple br url to srcs
			//return $this->parsePlaylist($u);
			
			$infos = $this->parsePlaylist($u);

			// set m3u8 playlist to srcs
			$infos['srcs'] = array($url);
			return $infos;

		} else {
			$this->error("content parse error: \n".$con);
			return false;
		}
	} // END parseStream





	/**
	 * parsePlaylist
	 *
	 * @return	void
	 */
	public function parsePlaylist( $url, $con = null )
	{ // BEGIN parsePlaylist
		$L = $this->LOG;
		$C =& $this->cnf;

		if (is_null($con)) {
			//$url = $C['url'];
			$con = Net::curl($url);
			$L->trace('touch: '.$url);

			if (empty($con)) {
				$this->error('playlist content empty.');
				return false;
			}
		}
		


		$idx = array(array(),array());
		// parse m3u8 ts



		// parse sequence
		if (preg_match('/\#EXT-X-MEDIA-SEQUENCE\:(\d+)/i', $con, $m)) {
			$sequence = (int)$m[1];
		} else {
			$sequence = 0;
		}
		$L->trace('parse sequence: '.$sequence);



		// // parse 
		// if (preg_match('/\#EXTINF\:.*?\n([^\s]*)/i', $con, $m)) {
		// 	$fu = $m[1];
		// 	if ( substr($fu, 0, 4) != 'http' ) {
		// 		$prefix = substr($fu, 0, 1) == '/' ? '' : '/' ;
		// 		$fu = dirname($url).$prefix.$fu;
		// 	}

		// 	//$infos = $this->getVideoInfo($fu);
		// } else {
		// 	$this->error('content parse error: '.$con);
		// 	return false;
		// }


		$_srcs = array();
		// parse positions
		$duration = 0;
		if (preg_match_all('/\#EXTINF\:([\d\.]+)[^\s]*\s+([^\s]+)/i', $con, $m)) {

			foreach ($m[1] as $k => $v) {
				$v = (int)$v;
				$idx[0][] = $duration*1000;
				$idx[1][] = $sequence++;
				$duration += $v;
				
				$_srcs[] = $this->formatRelativeUrl($m[2][$k], $url);
			}
		} else {
			$this->error('content parse error: '.$con);
			return false;
		}

		if ( false === strpos($con, '#EXT-X-ENDLIST') ) {
			$duration = 0;
		}


		$L->trace('ts count: '.count($_srcs));

		return array(
			'srcs' => array($url),
			'_srcs' => $_srcs,
			'idx' => array($idx),
			'duration' => $duration,
			'm3u8src_content' => $con,
			//'videoinfo' => array($infos),
		);
	} // END parsePlaylist



	/**
	 * m3u8srcReplaceFetch
	 *
	 * @return	void
	 */
	public function m3u8srcReplaceFetch( )
	{ // BEGIN m3u8srcReplaceFetch
		$L = $this->LOG;
		$C =& $this->cnf;

		$con = $C['m3u8src_content'];
		//$GLOBALS['_srcs'] = $C['srctype'] == 'fetch' ? $C['_fetchSrcs'] : $C['_origSrcs'];
		$GLOBALS['_srcs'] =  $C['_fetchSrcs'] ;
		$GLOBALS['_srcsi'] = 0;

		$s = preg_replace_callback('/(\#EXTINF\:[\d\.]+[^\s]*\s+)([^\s]+)/i', function ($m) {
			return $m[1].$GLOBALS['_srcs'][$GLOBALS['_srcsi']++];
		}, $con);
		
		$this->set('m3u8_'.$C['id'], $s, null, false);
		$C['origSrcs'] = $C['fetchSrcs'] = array( str_replace( array('|id|'), array($C['id']), $C['_m3u8_location'] ) );

		$C['m3u8src_content'] = $s;

		return $s;

	} // END m3u8srcReplaceFetch


	/**
	 * formatRelativeUrl
	 *
	 * @return	void
	 */
	public function formatRelativeUrl( $srcurl, $baseurl )
	{ // BEGIN formatRelativeUrl
		$srcinfo = parse_url($srcurl);
		if(isset($srcinfo['scheme'])) {
			return $srcurl;
		}
		$baseinfo = parse_url($baseurl);
		$url = $baseinfo['scheme'].'://'.$baseinfo['host'];
		if(substr($srcinfo['path'], 0, 1) == '/') {
			$path = $srcinfo['path'];
		}else{
			$path = dirname($baseinfo['path']).'/'.$srcinfo['path'];
		}
		$rst = array();
		$path_array = explode('/', $path);
		if(!$path_array[0]) {
			$rst[] = '';
		}
		foreach ($path_array AS $key => $dir) {
			if ($dir === '..') {
				if (end($rst) === '..') {
					$rst[] = '..';
				}elseif(!array_pop($rst)) {
					$rst[] = '..';
				}
			}elseif( $dir !== '' && $dir !== '.') {
				$rst[] = $dir;
			}
		}
		if(!end($path_array)) {
			$rst[] = '';
		}
		$url .= implode('/', $rst);
		if ( isset($srcinfo['query']) ) {
			$url .= '?'.$srcinfo['query'];
		}
		return str_replace('\\', '/', $url);
	} // END formatRelativeUrl





































	/**
	 * genM3u8ByUrl
	 *
	 * @return	void
	 */
	public function genM3u8ByUrl( $argu=array(), $rargu=array() )
	{ // BEGIN genM3u8ByUrl
		// get url
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$C['redirectM3u8'] = true;


		$this->prepareByUrl();

		// get idx==0 src
		// $this->updateSrcUrls(0);
		// if (empty($C['srcs_parse_completed']))
		// {
		// 	$L->trace('generate gradual m3u8.');
		// 	$this->updateInfos(0);
		// 	$this->updateM3u8(0);
		// 	$L->trace('continue generate m3u8.');
		// 	$this->updateSrcUrls();
		// }

		



		$this->updateSrcUrls();

		if ( !empty($C['su']) || !empty($C['mu']) || !empty($C['eu']) ) {
			$this->set($C['id'].'_playlist', array(
				'su' => empty($C['su']) ? array() : explode('#', $C['su']) ,
				'mu' => empty($C['mu']) ? array() : explode('#', $C['mu']) ,
				'eu' => empty($C['eu']) ? array() : explode('#', $C['eu']) ,
			));
		}
		


		if ( empty($C['m3u8_fast_response']) ) {
			$this->updateInfos();
		} else {
			$this->updateInfos(0);
			if ( empty($C['infos_parse_completed']) ) {
				$L->trace('generate gradual m3u8.');
				$this->updateM3u8(0);
				
				// continue parse infos
				$this->updateInfos();
				$L->trace('continue generate m3u8.');
			}
		}
		
		if ( !empty($C['medialog']) ) {
			// for certus
			if ( !empty($C['infos'][0]) ) {
				$infos = $C['infos'][0];
				$mux = empty($infos['mux']) ? '-' : $infos['mux'] ;
				$res = empty($infos['vw']) ? '-' : $infos['vw'].'x'.$infos['vh'];
				$br = empty($infos['br']) ? '-' : $infos['br'];
				$vc = empty($infos['vc']) ? '-' : $infos['vc'];
				$ac = empty($infos['ac']) ? '-' : $infos['ac'];
				$fps = empty($infos['fps']) ? '-' : $infos['fps'];
				//vcodecs
			} else {
				$mux = $res = $br = $vc = $ac = $fps = '-';
			}

			$dur = empty($C['duration']) ? '-' : $C['duration'] ;
			$size = empty($C['size']) ? '-' : $C['size'] ;

			$ip = empty($_SERVER["HTTP_X_REAL_IP"]) ? (empty($_SERVER['X_FORWARDED_FOR']) ? '-' : $_SERVER['X_FORWARDED_FOR']) : $_SERVER["HTTP_X_REAL_IP"];

			
			$s = date('d/m/Y:H:i:s ')." $mux $ip $res $br $fps {$vc}:{$ac} $dur $size\n";
			file_put_contents($C['medialog'], $s, FILE_APPEND);
		}

		$this->updateM3u8();


		

		

		
	} // END genM3u8ByUrl


	/**
	 * refreshAndRedirectSrc
	 *
	 * @return	void
	 */
	public function refreshAndRedirectSrc( $argu=array(), $rargu=array() )
	{ // BEGIN refreshAndRedirectSrc
		$L = $this->LOG;
		$C =& $this->cnf;

		$vid = $C['vid'];
		$idx = $C['idx'];
		$url = $C['url'];
		$L->trace('refresh and redirect src '.$vid.'['.$idx.']: '.$url);

		$C['forceUpdateSrcs'] = true;
		$this->prepareByUrl();
		$this->updateSrcUrls();
		$this->updateInfos();

		if (empty($C['srcs'][$idx])) {
			$this->error('not found', 404);
			exit;
		}

		Net::redirectAndContinue($C['srcs'][$idx]);
	} // END refreshAndRedirectSrc



	/**
	 * getSrcUrls
	 *
	 * @return	void
	 */
	public function getSrcUrls( $argu=array(), $rargu=array() )
	{ // BEGIN getSrcUrls
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$this->prepareByUrl();
		$this->updateSrcUrls();

		if ($C['rtype'] == 'json') {
			$d = array(
				'srcs' => $C['srcs'],
				'durations' => $C['durations'],
			);
			$ds = json_encode($d);
			if (!empty($C['callback'])) {
				$ds = "{$C['callback']}($ds);";
			}
			header('Content-type: application/json');
			$L->trace("echo src urls json:\n".$ds);
			echo $ds;
		} else {

			$us = implode("\n", $C['srcs']);
			$L->trace("echo src urls string:\n".$us);

			echo $us;
		}
	} // END getSrcUrls


	/**
	 * getOrigSrcs
	 *
	 * @return	void
	 */
	public function getOrigSrcs( $argu=array(), $rargu=array() )
	{ // BEGIN getOrigSrcs
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$this->prepareByUrl();
		$this->updateSrcUrls();

		if ($C['rtype'] == 'json') {
			$d = array(
				'srcs' => $C['origSrcs'],
				'durations' => $C['durations'],
			);
			$ds = json_encode($d);
			if (!empty($C['callback'])) {
				$ds = "{$C['callback']}($ds);";
			}
			header('Content-type: application/json');
			$L->trace("echo orig src urls json:\n".$ds);
			echo $ds;
		} else {
			$us = implode("\n", $C['origSrcs']);
			$L->trace("echo orig src urls string:\n".$us);
			echo $us;
		}

	} // END getOrigSrcs

	/**
	 * checkOrigSrcs
	 *
	 * @return	void
	 */
	public function checkOrigSrcs( $argu=array(), $rargu=array() )
	{ // BEGIN checkOrigSrcs
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$this->prepareByUrl();
		$this->updateSrcUrls();

		if ($C['rtype'] == 'json') {
			$d = array(
				//'count' => count($C['origSrcs']),
				'success' => empty($C['isbak']) ? true : false,
			);
			$ds = json_encode($d);
			if (!empty($C['callback'])) {
				$ds = "{$C['callback']}($ds);";
			}
			header('Content-type: application/json');
			$L->trace("echo orig src urls json:\n".$ds);
			echo $ds;
		} else {
			$us = 'success';
			$L->trace("echo orig src urls string:\n".$us);
			echo $us;
		}
	} // END checkOrigSrcs


	/**
	 * getVideoInfo
	 *
	 * @return	void
	 */
	public function getVideoInfo( $argu=array(), $rargu=array() )
	{ // BEGIN getVideoInfo
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$this->prepareByUrl();
		$this->updateSrcUrls();

		$info = array();

		if (!empty($C['videoinfo'][0])) {
			$info['width'] = $C['videoinfo'][0]['width'];
			$info['height'] = $C['videoinfo'][0]['height'];
			//$info['duration'] = empty($C['videoinfo'][0]['duration']) ? -1 : $C['videoinfo'][0]['duration'];
		}

		$d = array(
			'info' => $info,
		);
		$ds = json_encode($d);
		if (!empty($C['callback'])) {
			$ds = "{$C['callback']}($ds);";
		}
		header('Content-type: application/json');
		$L->trace("echo videoinfo json:\n".$ds);
		echo $ds;

	} // END getVideoInfo


	/**
	 * getVideoInfos
	 *
	 * @return	void
	 */
	public function getVideoInfos( $argu=array(), $rargu=array() )
	{ // BEGIN getVideoInfos
		
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$this->prepareByUrl();
		$this->updateSrcUrls();

		$infos = array();

		foreach ($C['videoinfo'] as $i => $videoinfo) {
			$infos[$i]['width'] = $videoinfo['width'];
			$infos[$i]['height'] = $videoinfo['height'];
			$infos[$i]['duration'] = empty($videoinfo['duration']) ? -1 : $videoinfo['duration'];
		}

		$d = array(
			'total' => $C['total'],
			'infos' => $infos,
		);
		$ds = json_encode($d);
		if (!empty($C['callback'])) {
			$ds = "{$C['callback']}($ds);";
		}
		header('Content-type: application/json');
		$L->trace("echo videoinfos json:\n".$ds);
		echo $ds;

	} // END getVideoInfos


	/**
	 * getDTM3u8
	 *
	 * @return	void
	 */
	public function getDTM3u8( $argu=array(), $rargu=array() )
	{ // BEGIN getDTM3u8
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		//$this->prepareByUrl();
		//$this->updateSrcUrls();

		//$C['redirectM3u8'] = true;
		//$this->updateM3u8();

		$c = Net::curl($C['webparser'].urlencode($C['url']));

		if (empty($c)) {
			$this->error('webparser get error.');
		} else {
			$url = trim($c);
			$L->trace('Location: '.$url);
			header('Location: '.$url);
		}
	} // END getDTM3u8


	/**
	 * getMergeUrl
	 *
	 * @return	void
	 */
	public function getMergeUrl( $argu=array(), $rargu=array() )
	{ // BEGIN getMergeUrl
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$this->prepareByUrl();
		$this->updateSrcUrls();

		$us = implode("[|]", $C['srcs']);

		$L->trace("include merge class: ".$C['merge_location']);
		
		// config
		$cnf = include $C['merge_location'].'config.php';
		$cnf['LOG'] = $L;
		$cnf['DB'] = $this->DB;
		$cnf['CDB'] = $this->CDB;
		$cnf['CDB2'] = $this->CDB2;
		$cnf['PARENT'] = $this;

		// include class merge
		include $C['merge_location'].'merge.php';

		$o = new Merge($cnf);

		$I = array(
			'rtype' => $C['rtype'],
			'callback' => $C['callback'],
			'mode' => $C['mode'],
			'u' => $us,
			'idx' => is_numeric($C['idx']) ? $C['idx'] : 0 ,
			'force' => $C['force'],
			'expire' => $C['siteCnf']['et_srcs'],
			'vid' => $C['id'],
		) + $rargu;


		// call merge
		$o->main($I, $I);

	} // END getMergeUrl

	/**
	 * getRtspUrl
	 *
	 * @return	void
	 */
	public function getRtspUrl( $argu=array(), $rargu=array() )
	{ // BEGIN getRtspUrl
		$L = $this->LOG;
		$C =& $this->cnf;

		$L->trace('parse url: '.$C['url']);

		$C['redirectRtsp'] = true;

		// su eu add to srcs
		$C['upl'] = true;
		$C['params']['su'] = $C['su'];
		$C['params']['eu'] = $C['eu'];

		$this->prepareByUrl();

		$this->updateSrcUrls();

		if ( empty($C['siteCnf']['isRTSP']) ) {
			if ( empty($C['duration']) ) {
				$this->updateInfos();
				$this->redirectRtsp();
			} else {
				$this->updateInfos(0);
				$this->redirectRtsp();
				$this->updateInfos();
			}
			
		} else {
			$this->setRtspInfos();
			$this->redirectRtsp();
		}


	} // END getRtspUrl



	/**
	 * setRtspInfos
	 *
	 * @return	void
	 */
	public function setRtspInfos( $argu=array(), $rargu=array() )
	{ // BEGIN setRtspInfos
		$L = $this->LOG;
		$C =& $this->cnf;

		$C['infos'] = array(
			'total' => count($C['origSrcs']),
			'infos' => array(),
		);
		foreach ($C['origSrcs'] as $i => $src) {
			$s = array(
				'uri' => $src,
			);
			$C['infos']['infos'][$i] = $s;
			$this->set($C['id'].$i.'_infos', $s, $C['siteCnf']['et_infos'], true);
		}

		$this->set($C['id'].'_infos', $C['infos'], $C['siteCnf']['et_infos'], false);

	} // END setRtspInfos

	/**
	 * redirectRtsp
	 *
	 * @return	void
	 */
	public function redirectRtsp(  )
	{ // BEGIN redirectRtsp
		$L = $this->LOG;
		$C =& $this->cnf;

		if (empty($C['redirectedRtsp']))
		{
			if (empty(PC::$RI['rtsp_location']))
			{
				$ipmode=0;
				if (isset($_GET['ipmode']))
				{
					$ipmode=(int)$_GET['ipmode'];
				}
				else if ( !empty($_SERVER['HTTP_USER_AGENT']) && preg_match('/ip=(\d+)/i', $_SERVER['HTTP_USER_AGENT'], $m))
				{

					$ipmode=(int)$m[1];
				}
				else
				{
					$_s= empty($_SERVER["HTTP_X_REAL_IP"]) ? '' : substr($_SERVER["HTTP_X_REAL_IP"], 0, 3);
					// xm 172.25.3.199 only
					if ('172' == $_s || '119' == $_s)
						$ipmode = 1;
				} 
				if ($ipmode >= count($C['rtsp_location']))
					$ipmode=0;
				$location = $C['rtsp_location'][$ipmode];
			}
			else
			{
				$location = PC::$RI['rtsp_location'];
			}

			$mux = $C['mux'];
			if ($mux == 'mp2ts') {
				$mux = 'ts';
			}

			$rUrl = str_replace( array('|vid|','|id|','|profile|','|mux|','|duration|'), array($C['vid'],$C['id'],$C['profile'],$mux,$C['duration']), $location);
			$L->trace('redirect to: '.$rUrl);
			Net::redirectAndContinue($rUrl);
			$C['redirectedRtsp'] = true;
		}
	} // END redirectRtsp


	/**
	 * Main
	 *
	 * @param	array	$argu
	 */
	public function main( $argu=array(), $rargu=array() )
	{ // BEGIN main

		$C =& $this->cnf;
		$L = $this->LOG;

		$L->trace('----------------------------------------------------------');
		$ua = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'] ;
		$L->trace("{$_SERVER['REMOTE_ADDR']} http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']} {$ua}");



		$C['mode'] = empty($argu['mode']) ? 'genM3u8ByUrl' : $argu['mode'];

		$C['url'] = empty($rargu['u'])? '': $rargu['u'];
		$C['idx'] = isset($rargu['idx']) && is_numeric($rargu['idx'])? $rargu['idx'] : '';
		$C['id'] = empty($rargu['vid'])? '': $rargu['vid'];
		//$C['playmode'] = empty($rargu['playmode']) ? '' : $rargu['playmode'] ;
		$C['playmode'] = 'play';
		$C['debug'] = isset($rargu['debug'])? (!empty($rargu['debug'])) : $C['debug'];
		$C['displaylog'] = empty($rargu['displaylog'])? false: true;

		if (isset($rargu['force'])) {
			$C['force'] = empty($rargu['force'])? false: $rargu['force'];
		} else {
			$C['force'] = empty($rargu['fr'])? false: $rargu['fr'];
		}
		

		$C['rtype'] = empty($rargu['rtype'])? '': $rargu['rtype'];
		$C['callback'] = empty($rargu['callback'])? '': $rargu['callback'];

		if ( !empty($C['id']) ) {
			$C['vid'] = $this->get($C['id'].'_vid', 1);
			if ( empty($C['vid']) ) {
				$C['vid'] = $C['id'];
			}
		}


		// refresh
		if (empty($C['url']) && !empty($C['vid']))
		{
			$_GET['u'] = $C['url'] = $this->get($C['vid'].'_url', 1);
			$C['parsesrc_params'] = $this->get($C['id'].'_parsesrc_params', 1);
			$C['params'] = $this->get($C['vid'].'_params', 1);
			if (!empty($C['params']) && is_array($C['params'])) {
				PC::$RI = PC::$I = $_GET = $argu = $rargu = $C['params'] + $rargu;
			} else {
				$C['params'] = array();
			}
		} else {
			$paramsKeys = explode(',', 'quality,seek,profile,offline,dt,pt,ct_location,srctype,site,ptype,seg,hd,uc,cdn,ts_location,checkmux');
			$C['params'] = array();
			foreach ($paramsKeys as $key)
			{
				if (isset($rargu[$key])) {
					$C['params'][$key] = $rargu[$key];
				}
			}
		}

		if (empty($C['url']))
			$this->error('url param empty or vid not cached.');



		$C['offline'] = empty($rargu['offline']) ? 0 : 1;
		$C['dt'] = 0;
		$C['quality'] = empty($rargu['quality']) ? 'n' : $rargu['quality'] ;
		$C['profile'] = empty($rargu['profile']) ? $C['profile'] : $rargu['profile'] ;
		$C['seek'] = empty($rargu['seek']) ? 'n' : $rargu['seek'] ;
		$C['srctype'] = empty($rargu['srctype']) ? $C['srctype'] : $rargu['srctype'] ;
		$C['checkmux'] = empty($rargu['checkmux']) ? (isset($C['checkmux']) ? $C['checkmux']: false) : $rargu['checkmux'] ;

		$C['forceSite'] = empty($rargu['site']) ? '' : $rargu['site'] ;
		$C['seg'] =  ( isset($rargu['seg']) && is_numeric($rargu['seg']) ) ? (int)$rargu['seg'] : false;
		$C['hd'] = empty($rargu['hd']) ? '' : $rargu['hd'] ;
		$C['mux'] = empty($rargu['mux']) ? 'mp4' : $rargu['mux'] ;
		$C['uc'] = empty($rargu['uc']) ? false : true ;
		$C['cdn'] = empty($rargu['cdn']) ? 0 : (int)$rargu['cdn'] ;
		$C['ptype'] = empty($rargu['ptype']) ? '' : $rargu['ptype'] ;

		$C['su'] = empty($rargu['su']) ? '' : $rargu['su'] ;
		$C['mu'] = empty($rargu['mu']) ? '' : $rargu['mu'] ;
		$C['eu'] = empty($rargu['eu']) ? '' : $rargu['eu'] ;

		$C['ts_location'] = empty($rargu['ts_location']) ? $C['ts_location'] : $rargu['ts_location'] ;

		$C['upl'] = false;

		// check hversion
		
		if (empty($rargu['hversion'])) {
			$C['hversion'] = '';
		} else {
			$C['hversion'] = $rargu['hversion'];
			foreach ($C['hversion_type'] as $k => $v) {
				if (in_array($rargu['hversion'], $v)) {
					$C['hversion'] = $k;
					break;
				}
			}
			$C['params']['hversion'] = $C['hversion'];
			$_GET['hversion'] = $argu['hversion'] = $rargu['hversion'] = $C['hversion'];
		}




		$C['forceUpdateSrcs'] = empty($C['force']) ? false : true ;





		// get flags
		$f = 0;
		if (isset($_GET['pt']))
			$C['pt'] = !empty($_GET['pt']);

		$f |= $C['pt']?1:0;
		$C['flags'] = $f;



		// guess working mode
		if (empty($argu['mode']))
		{
			if (!empty($argu['vid']) && isset($argu['idx']))
			{
				$C['mode'] = 'refreshAndRedirectSrc';
			}
			else if (!empty($argu['vid']))
			{
				$C['mode'] = 'genM3u8ByVid';
			}
		}



		

		// set working mode
		$L->trace('WORK MODE: '.$C['mode']);

		// execute mode
		switch ($C['mode'])
		{
			case 'dt':
				$C['dt'] = 1;
				$C['flags'] |= 2;
				return $this->getDTM3u8();
				break;

			case 'refreshAndRedirectSrc':
				$C['playmode'] = 'play';
				return $this->refreshAndRedirectSrc($argu, $rargu);
				break;


			case 'genM3u8ByVid':
				$C['playmode'] = 'play';
				return $this->genM3u8ByUrl($argu, $rargu);
				break;

			case 'getSrcUrls':
				return $this->getSrcUrls($argu, $rargu);
				break;

			case 'getOrigSrcs':
				return $this->getOrigSrcs($argu, $rargu);
				break;

			case 'checkOrigSrcs':
				return $this->checkOrigSrcs($argu, $rargu);
				break;


			case 'redirectMergeLocation':
				$C['playmode'] = 'play';
				$C['force'] = true;
				$C['forceUpdateSrcs'] = true;
				$C['mode'] = 'redirectMergeLocation';
				$_GET['get_real_src'] = 0;
				return $this->getMergeUrl($argu, $rargu);
				break;

			case 'getMergeUrl':
				$C['playmode'] = 'play';
				$C['mode'] = 'mergeUrl';
				$_GET['get_real_src'] = 0;
				return $this->getMergeUrl($argu, $rargu);
				break;

			case 'getRtspUrl':
				$C['playmode'] = 'play';
				$_GET['get_real_src'] = 0;
				return $this->getRtspUrl($argu, $rargu);
				break;

			case 'getVideoInfo':
				$_GET['get_real_src'] = 0;
				if ( isset($_GET['seg']) && is_numeric($_GET['seg']) ) {
					return $this->getVideoInfo($argu, $rargu);
				} else {
					return $this->getVideoInfos($argu, $rargu);
				}
				break;

			case 'getVideoInfos':
				$_GET['get_real_src'] = 0;
				return $this->getVideoInfos($argu, $rargu);
				break;


			case 'genM3u8ByUrl':
			default:
				return $this->genM3u8ByUrl($argu, $rargu);
				break;
		}

	} // END main

} // END class Reveal


