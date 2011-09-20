<?php
	define('CARBON_SERVER','127.0.0.1');
	define('CARBON_PORT','9000');

	$graphite_data = array();
	$pages = array(array('page'=>'http://www.google.com','graphite_path'=>'pageload.google'));
	foreach ($pages as $row)
	{
		// /usr/bin/trickle -s -d 500 -u 100 -L 50 => Simulate a client with a 500 kbps download rate, 100kbps upload rate, and a 50ms latency.
		// No idea how realistic those values are, but they are a lot closer to the truth then a 100mb lan connection
		$data = json_decode(shell_exec('DISPLAY=:0 /usr/bin/trickle -s -d 500 -u 100 -L 50 /usr/bin/phantomjs netsniff.js '.$row['page']),true);
		$requestcount = $totalsize = 0;
		$min_start_time = 10000000000;
		$max_end_time = 0;
		foreach ($data['log']['entries'] as $cur)
		{
			$request_duration = $cur['time'] / 1000;
			$resp_size = floatval($cur['response']['bodySize']);

			// Extract the milliseconds since strtotime doesn't seem to retain it
			preg_match("/(.*)T(.*)\.(.*)(Z)/", $cur['startedDateTime'], $out);
			$milli = $out[3];

			$start_time = floatval(strtotime($cur['startedDateTime']) . "." . $milli);
			$end_time = $start_time + $request_duration;

			if ( $start_time < $min_start_time )
				$min_start_time = $start_time;

			if ( $end_time > $max_end_time )
				$max_end_time = $end_time;


			echo $cur['request']['url'].' '.$cur['time']."\n";;
			$requestcount++;
			$totalsize += $resp_size;
		}
		$totaltime = $max_end_time-$min_start_time;
		echo "Total: {$requestcount} requests, {$totalsize} bytes, {$totaltime} s\n";
		$graphite_data[] = ".{$row['graphite_path']}.requests {$requestcount} ".intVal(time()/300)*300;
		$graphite_data[] = ".{$row['graphite_path']}.bytes {$totalsize} ".intVal(time()/300)*300;
		$graphite_data[] = ".{$row['graphite_path']}.time {$totaltime} ".intVal(time()/300)*300;
	}
	var_dump($graphite_data);

	$f = fsockopen('tcp://'.CARBON_SERVER,CARBON_PORT);
	if (!$f)
	{
		die("Unable to connect to carbon!\n");
	}
	fwrite($f,implode("\n",$graphite_data)."\n");
	fclose($f);