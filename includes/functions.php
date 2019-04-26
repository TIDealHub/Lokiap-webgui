<?php
/* Functions for Networking */

function mask2cidr($mask)
{
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32-log(($long ^ $base)+1, 2);
}

/* Functions to write ini files */

function write_php_ini($array, $file)
{
    $res = array();
    foreach ($array as $key => $val) {
        if (is_array($val)) {
            $res[] = "[$key]";
            foreach ($val as $skey => $sval) {
                $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
            }
        } else {
            $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
        }
    }
    if (safefilerewrite($file, implode("\r\n", $res))) {
        return true;
    } else {
        return false;
    }
}

function safefilerewrite($fileName, $dataToSave)
{
    if ($fp = fopen($fileName, 'w')) {
        $startTime = microtime(true);
        do {
            $canWrite = flock($fp, LOCK_EX);
            // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
            if (!$canWrite) {
                usleep(round(rand(0, 100)*1000));
            }
        } while ((!$canWrite)and((microtime(true)-$startTime) < 5));

        //file was locked so now we can store information
        if ($canWrite) {
            fwrite($fp, $dataToSave);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return true;
    } else {
        return false;
    }
}



/**
*
* Add CSRF Token to form
*
*/
function CSRFToken()
{
    ?>
<input id="csrf_token" type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);
    ; ?>" />
<?php
}

/**
*
* Validate CSRF Token
*
*/
function CSRFValidate()
{
    if (hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])) {
        return true;
    } else {
        error_log('CSRF violation');
        return false;
    }
}

/**
* Test whether array is associative
*/
function isAssoc($arr)
{
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
*
* Display a selector field for a form. Arguments are:
*   $name:     Field name
*   $options:  Array of options
*   $selected: Selected option (optional)
*       If $options is an associative array this should be the key
*
*/
function SelectorOptions($name, $options, $selected = null, $id = null)
{
    echo '<select class="form-control" name="'.htmlspecialchars($name, ENT_QUOTES).'"';
    if (isset($id)) {
        echo ' id="' . htmlspecialchars($id, ENT_QUOTES) .'"';
    }

    echo '>' , PHP_EOL;
    foreach ($options as $opt => $label) {
        $select = '';
        $key = isAssoc($options) ? $opt : $label;
        if ($key == $selected) {
            $select = ' selected="selected"';
        }

        echo '<option value="'.htmlspecialchars($key, ENT_QUOTES).'"'.$select.'>'.
            htmlspecialchars($label, ENT_QUOTES).'</option>' , PHP_EOL;
    }

    echo '</select>' , PHP_EOL;
}

/**
*
* @param string $input
* @param string $string
* @param int $offset
* @param string $separator
* @return $string
*/
function GetDistString($input, $string, $offset, $separator)
{
    $string = substr($input, strpos($input, $string)+$offset, strpos(substr($input, strpos($input, $string)+$offset), $separator));
    return $string;
}

/**
*
* @param array $arrConfig
* @return $config
*/
function ParseConfig($arrConfig)
{
    $config = array();
    foreach ($arrConfig as $line) {
        $line = trim($line);
        if ($line != "" && $line[0] != "#") {
            $arrLine = explode("=", $line);
            $config[$arrLine[0]] = (count($arrLine) > 1 ? $arrLine[1] : true);
        }
    }
    return $config;
}

/**
*
* @param string $freq
* @return $channel
*/
function ConvertToChannel($freq)
{
    if ($freq >= 2412 && $freq <= 2484) {
        $channel = ($freq - 2407)/5;
    } elseif ($freq >= 4915 && $freq <= 4980) {
        $channel = ($freq - 4910)/5 + 182;
    } elseif ($freq >= 5035 && $freq <= 5865) {
        $channel = ($freq - 5030)/5 + 6;
    } else {
        $channel = -1;
    }
    if ($channel >= 1 && $channel <= 196) {
        return $channel;
    } else {
        return 'Invalid Channel';
    }
}

/**
* Converts WPA security string to readable format
* @param string $security
* @return string
*/
function ConvertToSecurity($security)
{
    $options = array();
    preg_match_all('/\[([^\]]+)\]/s', $security, $matches);
    foreach ($matches[1] as $match) {
        if (preg_match('/^(WPA\d?)/', $match, $protocol_match)) {
            $protocol = $protocol_match[1];
            $matchArr = explode('-', $match);
            if (count($matchArr) > 2) {
                $options[] = htmlspecialchars($protocol . ' ('. $matchArr[2] .')', ENT_QUOTES);
            } else {
                $options[] = htmlspecialchars($protocol, ENT_QUOTES);
            }
        }
    }

    if (count($options) === 0) {
        // This could also be WEP but wpa_supplicant doesn't have a way to determine
        // this.
        // And you shouldn't be using WEP these days anyway.
        return 'Open';
    } else {
        return implode('<br />', $options);
    }
}

/**
*
*
*/
function DisplayOpenVPNConfig()
{
    exec('cat '. RASPI_OPENVPN_CLIENT_CONFIG, $returnClient);
    exec('cat '. RASPI_OPENVPN_SERVER_CONFIG, $returnServer);
    exec('pidof openvpn | wc -l', $openvpnstatus);

    if ($openvpnstatus[0] == 0) {
        $status = '<div class="alert alert-warning alert-dismissable">OpenVPN is not running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    } else {
        $status = '<div class="alert alert-success alert-dismissable">OpenVPN is running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    }

    // parse client settings
    foreach ($returnClient as $a) {
        if ($a[0] != "#") {
            $arrLine = explode(" ", $a) ;
            $arrClientConfig[$arrLine[0]]=$arrLine[1];
        }
    }

    // parse server settings
    foreach ($returnServer as $a) {
        if ($a[0] != "#") {
            $arrLine = explode(" ", $a) ;
            $arrServerConfig[$arrLine[0]]=$arrLine[1];
        }
    } ?>
	<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-primary">
			<div class="panel-heading"><i class="fa fa-lock fa-fw"></i> Configure OpenVPN </div>
		<!-- /.panel-heading -->
		<div class="panel-body">
			<!-- Nav tabs -->
			<ul class="nav nav-tabs">
				<li class="active"><a href="#openvpnclient" data-toggle="tab">Client settings</a></li>
				<li><a href="#openvpnserver" data-toggle="tab">Server settings</a></li>
			</ul>
			<!-- Tab panes -->
			<div class="tab-content">
				<p><?php echo $status; ?></p>
				<div class="tab-pane fade in active" id="openvpnclient">

					<h4>Client settings</h4>
					<form role="form" action="?page=save_hostapd_conf" method="POST">

					<div class="row">
						<div class="form-group col-md-4">
							<label>Select OpenVPN configuration file (.ovpn)</label>
							<input type="file" name="openvpn-config">
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">Client Log</label>
							<input type="text" class="form-control" id="disabledInput" name="log-append" type="text" placeholder="<?php echo htmlspecialchars($arrClientConfig['log-append'], ENT_QUOTES); ?>" disabled="disabled" />
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="openvpnserver">
					<h4>Server settings</h4>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Port</label>
						<input type="text" class="form-control" name="openvpn_port" value="<?php echo htmlspecialchars($arrServerConfig['port'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Protocol</label>
						<input type="text" class="form-control" name="openvpn_proto" value="<?php echo htmlspecialchars($arrServerConfig['proto'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Root CA certificate</label>
						<input type="text" class="form-control" name="openvpn_rootca" placeholder="<?php echo htmlspecialchars($arrServerConfig['ca'], ENT_QUOTES); ?>" disabled="disabled" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Server certificate</label>
						<input type="text" class="form-control" name="openvpn_cert" placeholder="<?php echo htmlspecialchars($arrServerConfig['cert'], ENT_QUOTES); ?>" disabled="disabled" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Diffie Hellman parameters</label>
						<input type="text" class="form-control" name="openvpn_dh" placeholder="<?php echo htmlspecialchars($arrServerConfig['dh'], ENT_QUOTES); ?>" disabled="disabled" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">KeepAlive</label>
						<input type="text" class="form-control" name="openvpn_keepalive" value="<?php echo htmlspecialchars($arrServerConfig['keepalive'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
						<label for="code">Server log</label>
						<input type="text" class="form-control" name="openvpn_status" placeholder="<?php echo htmlspecialchars($arrServerConfig['status'], ENT_QUOTES); ?>" disabled="disabled" />
						</div>
					</div>
				</div>
				<input type="submit" class="btn btn-outline btn-primary" name="SaveOpenVPNSettings" value="Save settings" />
				<?php
                if ($hostapdstatus[0] == 0) {
                    echo '<input type="submit" class="btn btn-success" name="StartOpenVPN" value="Start OpenVPN" />' , PHP_EOL;
                } else {
                    echo '<input type="submit" class="btn btn-warning" name="StopOpenVPN" value="Stop OpenVPN" />' , PHP_EOL;
                } ?>
				</form>
		</div><!-- /.panel-body -->
	</div><!-- /.panel-primary -->
	<div class="panel-footer"> Information provided by openvpn</div>
</div><!-- /.col-lg-12 -->
</div><!-- /.row -->
<?php
}

/**
*
*
*/
/*LOKINET FUNCTIONS ADDED HERE*/

function DisplayLokinetConfig()
{
    exec('pidof lokinet | wc -l', $lokinetstatus);
    $rulestate = exec("ip rule show default | grep lokinet | awk {'print $5'}", $output);
    if ($lokinetstatus[0] == 0) {
        $status = '<div class="alert alert-danger alert-dismissable">Lokinet daemon is not running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    } else {
        $status = '<div class="alert alert-success alert-dismissable">Lokinet daemon is running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    }
    if ($rulestate != "lokinet") {
        $status = '<div class="alert alert-danger alert-dismissable">Not Connected to Lokinet
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    } else {
        $status = '<div class="alert alert-success alert-dismissable">Successfully Connected to Lokinet
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    }
     ?>
	<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-primary">
			<div class="panel-heading"><i class="fa fa-eye-slash fa-fw"></i> Configure Lokinet</div>
        <!-- /.panel-heading -->
        <div class="panel-body">
        	<!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <li class="active"><a href="#basic" data-toggle="tab">Daemon Control</a>
                </li>
                <li><a href="#daemon" data-toggle="tab">Advanced Console User</a>
                </li>
            </ul>
            <!-- Tab panes -->
           	<div class="tab-content">
           		<p><?php echo $status; ?></p>
            	<div class="tab-pane fade in active" id="basic">
            		<h4>Main Settings</h4>
					<form role="form" action="?page=save_hostapd_conf" method="POST">
            <div class="row">
						<div class="form-group col-lg-6">
							<label for="code">All 4 Buttons must be green to connect to Lokinet.If there is no .ini file it must be generated followed by applying a bootstrap. If no URL is submitted the default bootstrap file will be applied automatically.</label>
              <div class="container">
                <h5>Entering and applying a valid bootstrap url below overwrites the current bootstrap settings:</h5>
                  <form>
                    <div class="form-group">
                      <label for="usr">Bootstrap url:</label>
                      <input type="url" class="form-control" placeholder="http://206.81.100.174/n-st-5.signed" id="lokinetbootstrap" name="lokinetbootstrap">
                    </div>
                  </form>
                </div>
                  <h5>Contact Loki user groups for the latest bootstrap file location</h5>
          				<?php
                  if ($rulestate != "lokinet") {
                      echo '<input type="submit" class="btn btn-danger" name="UseLokinet" value="Use Lokinet" />' , PHP_EOL;
                  } else {
                      echo '<input type="submit" class="btn btn-success" name="ExitLokinet" value="Exit Lokinet" />' , PHP_EOL;
                  }
                  if ($lokinetstatus[0] == 0) {
                      echo '<input type="submit" class="btn btn-danger" name="StartDaemon" value="Start Daemon" />' , PHP_EOL;
                  } else {
                      echo '<input type="submit" class="btn btn-success" name="StopDaemon" value="Stop Daemon" />' , PHP_EOL;
                        }

    $filename = '/usr/local/bin/lokinet.ini';

    if (file_exists($filename)) {
        echo '<input type="submit" class="btn btn-success" name="ReGenerateLokinet" value="Regenerate .ini" />' , PHP_EOL;
    } else {
        echo '<input type="submit" class="btn btn-danger" name="GenerateLokinet" value="Generate .ini" />' , PHP_EOL;
    } ?>
                  <input type="submit" class="btn btn-success" name="ApplyLokinetSettings" value="Re-Bootstrap" />
                  <h5><strong><?php echo _("Your development support is greatly appreciated: Developer Loki Address"); ?></strong></h5>
                  <h5><strong><pre><?php echo _("LA8VDcoJgiv2bSiVqyaT6hJ67LXbnQGpf9Uk3zh9ikUKPJUWeYbgsd9gxQ5ptM2hQNSsCaRETQ3GM9FLDe7BGqcm4ve69bh"); ?></pre></strong></h5>
				       </div>
             </div>
           </div>
             	<div class="tab-pane fade" id="daemon">
            		<h4>Lokient Daemon</h4>
                <div class="row">
                  <div class="col-lg-12">
                    <iframe src="includes/webconsole.php" class="webconsole"></iframe>
                  </div>
                </div>
              </div>
            </form>
      			</div><!-- /.tab-content -->
      		</div><!-- /.panel-body -->
      		<div class="panel-footer"> Information provided by Lokinet</div>
          </div><!-- /.panel-primary -->
      </div><!-- /.col-lg-12 -->
    </div><!-- /.row -->
      <?php
}


/**
*
*
*/
function DisplayTorProxyConfig()
{
    exec('cat '. RASPI_TORPROXY_CONFIG, $return);
    exec('pidof tor | wc -l', $torproxystatus);

    if ($torproxystatus[0] == 0) {
        $status = '<div class="alert alert-warning alert-dismissable">TOR is not running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    } else {
        $status = '<div class="alert alert-success alert-dismissable">TOR is running
					<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>';
    }

    $arrConfig = array();
    foreach ($return as $a) {
        if ($a[0] != "#") {
            $arrLine = explode(" ", $a) ;
            $arrConfig[$arrLine[0]]=$arrLine[1];
        }
    } ?>
	<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-primary">
			<div class="panel-heading"><i class="fa fa-eye-slash fa-fw"></i> Configure TOR proxy</div>
        <!-- /.panel-heading -->
        <div class="panel-body">
        	<!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <li class="active"><a href="#basic" data-toggle="tab">Basic</a>
                </li>
                <li><a href="#relay" data-toggle="tab">Relay</a>
                </li>
            </ul>

            <!-- Tab panes -->
           	<div class="tab-content">
           		<p><?php echo $status; ?></p>

            	<div class="tab-pane fade in active" id="basic">
            		<h4>Basic settings</h4>
					<form role="form" action="?page=save_hostapd_conf" method="POST">
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">VirtualAddrNetwork</label>
							<input type="text" class="form-control" name="virtualaddrnetwork" value="<?php echo htmlspecialchars($arrConfig['VirtualAddrNetwork'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">AutomapHostsSuffixes</label>
							<input type="text" class="form-control" name="automaphostssuffixes" value="<?php echo htmlspecialchars($arrConfig['AutomapHostsSuffixes'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">AutomapHostsOnResolve</label>
							<input type="text" class="form-control" name="automaphostsonresolve" value="<?php echo htmlspecialchars($arrConfig['AutomapHostsOnResolve'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">TransListenAddress</label>
							<input type="text" class="form-control" name="translistenaddress" value="<?php echo htmlspecialchars($arrConfig['TransListenAddress'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">DNSPort</label>
							<input type="text" class="form-control" name="dnsport" value="<?php echo htmlspecialchars($arrConfig['DNSPort'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">DNSListenAddress</label>
							<input type="text" class="form-control" name="dnslistenaddress" value="<?php echo htmlspecialchars($arrConfig['DNSListenAddress'], ENT_QUOTES); ?>" />
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="relay">
            		<h4>Relay settings</h4>
            		<div class="row">
						<div class="form-group col-md-4">
							<label for="code">ORPort</label>
							<input type="text" class="form-control" name="orport" value="<?php echo htmlspecialchars($arrConfig['ORPort'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">ORListenAddress</label>
							<input type="text" class="form-control" name="orlistenaddress" value="<?php echo htmlspecialchars($arrConfig['ORListenAddress'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">Nickname</label>
							<input type="text" class="form-control" name="nickname" value="<?php echo htmlspecialchars($arrConfig['Nickname'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">Address</label>
							<input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($arrConfig['Address'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">RelayBandwidthRate</label>
							<input type="text" class="form-control" name="relaybandwidthrate" value="<?php echo htmlspecialchars($arrConfig['RelayBandwidthRate'], ENT_QUOTES); ?>" />
						</div>
					</div>
					<div class="row">
						<div class="form-group col-md-4">
							<label for="code">RelayBandwidthBurst</label>
							<input type="text" class="form-control" name="relaybandwidthburst" value="<?php echo htmlspecialchars($arrConfig['RelayBandwidthBurst'], ENT_QUOTES); ?>" />
						</div>
					</div>
				</div>

				<input type="submit" class="btn btn-outline btn-primary" name="SaveTORProxySettings" value="Save settings" />
				<?php
                if ($torproxystatus[0] == 0) {
                    echo '<input type="submit" class="btn btn-success" name="StartTOR" value="Start TOR" />' , PHP_EOL;
                } else {
                    echo '<input type="submit" class="btn btn-warning" name="StopTOR" value="Stop TOR" />' , PHP_EOL;
                }; ?>
				</form>
			</div><!-- /.tab-content -->
		</div><!-- /.panel-body -->
		<div class="panel-footer"> Information provided by tor</div>
    </div><!-- /.panel-primary -->
</div><!-- /.col-lg-12 -->
</div><!-- /.row -->
<?php
}

/**
*
*
*/
function SaveTORAndVPNConfig()
{
    if (isset($_POST['SaveOpenVPNSettings'])) {
        // TODO
    } elseif (isset($_POST['SaveTORProxySettings'])) {
        // TODO
    } elseif (isset($_POST['StartOpenVPN'])) {
        echo "Attempting to start openvpn";
        exec('sudo /etc/init.d/openvpn start', $return);
        foreach ($return as $line) {
            echo htmlspecialchars($line, ENT_QUOTES).'<br />' , PHP_EOL;
        }
    } elseif (isset($_POST['StopOpenVPN'])) {
        echo "Attempting to stop openvpn";
        exec('sudo /etc/init.d/openvpn stop', $return);
        foreach ($return as $line) {
            echo htmlspecialchars($line, ENT_QUOTES).'<br />' , PHP_EOL;
        }
    } elseif (isset($_POST['StartTOR'])) {
        echo "Attempting to start TOR";
        exec('sudo /etc/init.d/tor start', $return);
        foreach ($return as $line) {
            echo htmlspecialchars($line, ENT_QUOTES).'<br />' , PHP_EOL;
        }
    } elseif (isset($_POST['StopTOR'])) {
        echo "Attempting to stop TOR";
        exec('sudo /etc/init.d/tor stop', $return);
        foreach ($return as $line) {
            echo htmlspecialchars($line, ENT_QUOTES).'<br />' , PHP_EOL;
        }
    } elseif (isset($_POST['StartDaemon'])) {
        ?>
    <div class="alert alert-success">
      Starting Lokinet background daemon process.
    </div>
    <?php
    $output = shell_exec('sudo /etc/init.d/dnsmasq stop');
    echo "<pre><strong>$output</strong></pre>";
    $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh start');
    echo "<pre><strong>$output</strong></pre>";
    $output = shell_exec('sudo /etc/init.d/dnsmasq start');
  # sleep(5);
  #  $output = shell_exec('sudo dnsmasq --interface=wlan0 --bind-interfaces --dhcp-range=10.3.141.0,10.3.141.24,12h --conf-file=/etc/resolv.conf');
    echo "<pre><strong>$output</strong></pre>";
  } elseif (isset($_POST['StopDaemon'])) {
        ?>
    <div class="alert alert-danger">
          Exiting Lokinet.
    </div>
    <?php
    $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh disconnect');
    echo "<pre><strong>$output</strong></pre>";
    ?>
    <div class="alert alert-danger">
      Stopping Lokinet background daemon process.
    </div>
    <?php
    $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh stop');
    echo "<pre><strong>$output</strong></pre>";

  } elseif (isset($_POST['UseLokinet'])) {
      ?>
  <div class="alert alert-success">
    Connecting to Lokinet.
  </div>
  <?php
  exec('pidof lokinet | wc -l', $lokinetstatus);
  $output = shell_exec('sudo /etc/init.d/dnsmasq stop');
  echo "<pre><strong>$output</strong></pre>";
  if ($lokinetstatus[0] == 0){
    $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh start');
    echo "<pre><strong>$output</strong></pre>";
  }
  $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh connect');
  echo "<pre><strong>$output</strong></pre>";
  $output = shell_exec('sudo /etc/init.d/dnsmasq start');
# sleep(5);
#  $output = shell_exec('sudo dnsmasq --interface=wlan0 --bind-interfaces --dhcp-range=10.3.141.0,10.3.141.24,12h --conf-file=/etc/resolv.conf');
  echo "<pre><strong>$output</strong></pre>";
} elseif (isset($_POST['ExitLokinet'])) {
      ?>
  <div class="alert alert-danger">
    Exiting Lokinet.
  </div>
  <?php
  $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh disconnect');
  echo "<pre><strong>$output</strong></pre>";

    } elseif (isset($_POST['GenerateLokinet'])) {
        ?>
    <div class="alert alert-success">
      Generating Lokinet Configuration
    </div>
    <?php
    $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh gen');
        echo "<pre><strong>$output</strong></pre>";
    } elseif (isset($_POST['ReGenerateLokinet'])) {
        ?>
    <div class="alert alert-success">
      Regenerating Lokinet Configuration
    </div>
    <?php
    $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh gen');
        echo "<pre><strong>$output</strong></pre>";
    } elseif (isset($_POST['ApplyLokinetSettings'])) {
      ?>
  <div class="alert alert-danger">
        Exiting Lokinet.
  </div>
  <?php
  $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh disconnect');
  echo "<pre><strong>$output</strong></pre>";
  ?>
  <div class="alert alert-danger">
    Stopping Lokinet background daemon process.
  </div>
  <?php
  $output = shell_exec('sudo /home/pi/loki-network/lokilaunch.sh stop');
  echo "<pre><strong>$output</strong></pre>";
        $bootstrap = $_POST['lokinetbootstrap'];
  ?>
  <div class="alert alert-success">
    Applying Bootstrap
  </div>
  <?php
  $bootstrap=str_replace("'", "", $bootstrap);
        $output = shell_exec('sudo /home/pi/./loki-network/lokilaunch.sh bootstrap '.$bootstrap.'');
        echo "<pre><strong>$output</strong></pre>";
    }
}
?>
