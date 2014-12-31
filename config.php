<?php

$log_file = (PHP_OS == 'WINNT') ? 'R:\reveal.log' : '/dev/shm/reveal.log' ; 

return array(
	'debug' => true,

	'log_config' => array(
			'file' => $log_file,
			'display_runtime' => 0,
			'dump_runtime' => false,
		),

	'vpos_path' => '/dev/shm/vpos/',
	
	'data_struct' => 'json', // default
	'db_url' => '127.0.0.1:8091',
	'cdb' => array('127.0.0.1:8091','coredata','11211!','coredata'),
	'cdb2' => array('127.0.0.1:8091','content','','content'),

	'db_server' => '127.0.0.1:8091',
	'mem_url' => '127.0.0.1:11211',
	
	// flags
	'pt' => 1,

	'del_tmp_file' => 1,

	'mode' => 'refreshByUrl',

	'tsmode' => 'db',

	// {vid} {idx} {start} {length} {bw}
	'ts_location' => '{idx}/{tsname}.ts?vs={vcspeed}&ca={offline}',

	
	'vcspeed_max_time' => 8,
	'm3u8_location' => array(array("http://127.0.0.1/|flags|/|id|.m3u8")),
	'm3u8_ts_length' => 10, //seconds
	'm3u8_fast_response' => 1,

	'rtsp_location' => array("rtsp://rt-dev.vsea.tv:554/ARTS/|profile|/|id|.|mux|"),
	'profile' => 'PT',

	'srctype' => 'fetch', // orig, fetch
	'fetch_location' => "http://127.0.0.1:8200/sfetch/|id|/|idx|",
	'_fetch_location' => "http://127.0.0.1:8200/ts/|id|/|idx|",
	'_m3u8_location' => "http://127.0.0.1/|id|.m3u8",

	'lock_timeout' => 30,


	'merge_location' => "../merge/",


	'parse_pos_limit' => 4,
	'parse_pos_each_timeout' => 30,
	'parse_pos_timeout' => 30,

	'vid_prefix' => '',


	'hversion_type' => array(
		'noffps' => array('00000842', '00000243', '00001141'),
	),


	'site_cnf_default' => array(
			'parsesrc_url' => 'http://127.0.0.1:8080/app/parsesrc/',
			'videoidxparse' => dirname(__FILE__)."/videoidxparse10 -H 'norefresh: 1' -f '{src}' -u '{ua}' -i '{id}' -a '{db}' -c content -l {timeout} -r '{id}_src' -t {vidxp} >{file} 2>&1 & echo $!",

			'areaSize' => 320*240,
			
			'et' => 86400,
			'et_main' => 7200,
			'et_srcs' => 14400,
			'et_fetch' => 86400,
			'et_infos' => 86400,

			'et_m3u8' => 24400,
			'et_gradual_m3u8' => 25,
			'et_ts_pos' => 24400,

			'et_vidxp' => 86400,
		),

	'site_cnfs' => array(
		
			'pl' => array(
				'pattern' => '/pl.youku.com\/playlist\/m3u8/i',
			),

			'm3u8' => array(
				'pattern' => '/^([^\?]+?(?:m3u8)(?:\?.*|))(?:[\?\#].*|\s*)$/i',
			),

			'rtsp' => array(
				'pattern' => '/^rtsp\:\/\/.*$/i',
				'isRTSP' => true,
			),

			'youkuad' => array(
				'pattern' => '/^http\:\/\/valf\.atm\.youku\.com\/.*$/i',
			),

			'sohuad' => array(
				'pattern' => '/^http\:\/\/v\.aty\.sohu\.com\/.*$/i',
			),

			't2' => array(
					'pattern' => '/m3u8test$/i',
				),

			'live' => array(
				'pattern' => '/kktv\d*\.com/i',
				'fakeinfos' => array(
					'duration' => '7200',
					'br' => '10240',
					'fps' => '25',
					'apl' =>'',
					'ac' => 'aac',
					'acodecs' =>'',
					'asr' => '44100',
					'achs' => '2 channels (FC)',
					'abr' => '32',
					'vc' => 'h264',
					'vcodecs' =>'',
					'vw' => '640',
					'vh' => '480',
					'tvw' => '368',
					'tvh' => '208',
					'uri' => '',
					'm3u8playlist' => '0',
					'idx' => array(),
					'aidx' => array(),
				),
			),

			'f' => array(
					array(
						'pattern' => '/kktv\d*\.com/i',
						'fakeinfos' => array(
							'duration' => '0',
							'br' => '10240',
							'fps' => '25',
							'apl' =>'',
							'ac' => 'aac',
							'acodecs' =>'',
							'asr' => '44100',
							'achs' => '2 channels (FC)',
							'abr' => '32',
							'vc' => 'h264',
							'vcodecs' =>'',
							'vw' => '640',
							'vh' => '480',
							'tvw' => '368',
							'tvh' => '208',
							'uri' => '',
							'm3u8playlist' => '0',
							'idx' => array(),
							'aidx' => array(),
						),
					),
					array(
						'pattern' => '/^([^\?]+?(:?ts)(?:\?.*|))(?:[\?\#].*|\s*)$/i',
						'isTS' => true,
					),
				),

			'vod' => array(
					'pattern' => '/^([^\?]+?(?:mp4|tfs|flv|ts|3gp|dat|f4v|avi|asf|wmv|avs|mkv|mov|mpg|mpeg|ogm|vob|rm|rmvb|tp|m2ts)(?:\?.*|))(?:[\?\#].*|\s*)$/i',
				),

			'youku' => array(
					'pattern' => '/^http\:\/\/v\.youku\.com\/v_show\/id_(.+?)\.html/i',
				),

			'tudouswf' => array(
					'pattern' => '/tudou\.com\/v\/(.*?)/i',
				),

			'tudou' => array(
					'pattern' => '/tudou\.com/i',
				),
			'56' => array(
					'pattern' => '/^http\:\/\/www\.56\.com\/[a-z0-9]+\/(?:v_|play_album).*/i',
				),
			'ku6' => array(
					'pattern' => '/^http\:\/\/v\.ku6\.com\/(?:special|film|show)\/.*/i',
				),
			'sina' => array(
					'pattern' => '/sina\.com\.cn/i',
				),

			'sohu' =>array(
					'pattern' => '/tv\.sohu\.com/i',
				),

			'qq' => array(
					'pattern' => '/qq\.com/i',
				),

			'qiyi' => array(
					'pattern' => '/qiyi\.com/i',
				),
			'yinyuetai' => array(
					'pattern' => '/yinyuetai\.com/i',
				),

			'letv' => array(
					'pattern' => '/letv\.com/i',
				),

			'ifeng' => array(
					'pattern' => '/ifeng\.com/i',
				),

			'pptv' => array(
				'pattern' => '/pptv\.com/i',
			),
			'pps' => array(
				'pattern' => '/pps\.tv/i',
			),
			'hunantv' => array(
				'pattern' => '/hunantv\.com/i',
			),
		),
	
);