<?php
ini_set('memory_limit', '-1');
/**
 * Require composer autoload class
 */
require 'vendor/autoload.php';

set_error_handler('errHandle');

function errHandle($errNo, $errStr, $errFile, $errLine) {
	$msg = "$errStr in $errFile on line $errLine";
	if ($errNo == E_NOTICE || $errNo == E_WARNING) {
		throw new ErrorException($msg, $errNo);
		return 1;
	} else {
		echo $msg;
	}
}

$AWSKey = getenv('AWS_KEY');
$AWSSecret = getenv('AWS_SECRET');

$config = array();
$config['key'] = $AWSKey;
$config['secret'] = $AWSSecret;
$config['region'] = $argv[1];

$ec2Client = \Aws\Ec2\Ec2Client::factory($config);

$result = $ec2Client->DescribeInstances(array(
    'DryRun' => false
));

switch ($config['region'])
{
	case "ap-northeast-1":
		$region = "Tokyo";
		break;
	case "ap-northeast-2":
		$region = "Seoul";
		break;
	case "ap-southeast-1":
		$region = "Singapore";
		break;
	case "ap-southeast-2":
		$region = "Sydney";
		break;
	case "eu-central-1":
		$region = "Frankfurt";
		break;
	case "eu-west-1":
		$region = "Ireland";
		break;
	case "sa-east-1":
		$region = "SaoPaulo";
		break;
	case "us-east-1":
		$region = "NorthVirginia";
		break;
	case "us-west-1":
		$region = "NorthCalifornia";
		break;
	case "us-west-2":
		$region = "Oregon";
		break;
	case "ap-south-1":
		$region = "Mumbai";
		break;
	case "ca-central-1":
		$region = "Canada (Central)";
		break;
	case "us-east-2":
		$region = "US East (Ohio)";
		break;
}

/**
 * Instances container
 */
$instances = [];
$nagiosInstances =[];

/**
 * Loop over results from AWS API
 */
$reservations = $result['Reservations'];

foreach ($reservations as $reservation)
{
	$instances = $reservation['Instances'];
	foreach ($instances as $instance)
	{
    /**
     * We do not need terminated instances
     */
		if ($instance['State']['Name'] <> "terminated" & isset($instance['Tags']))
		{
			$instanceName = '';
			$System = 'General-'.$region;
			$OS = "aws-linux-server";
			foreach ($instance['Tags'] as $tag)
			{
				switch ($tag['Key'])
				{
					case 'Name':
						$instanceName = $tag['Value'];
						break;
					case 'System':
						$System = $tag['Value'];
						break;
					case 'Prototype':
						if ($tag['Value'] == 'WINDOWS')
						{
							$OS = "aws-windows-server";
						}
				}
			}
    		/**
     		* Get instance information
     		*/
    		if (!isset($instance['PublicIpAddress']))
    		{
    			$instancePublicIP   =   '';
    		}	
    		else 
    		{
    			$instancePublicIP   =   $instance['PublicIpAddress'];
    		}
    		$instanceId         =   $instance['InstanceId'];
    		$instanceRegion		=	$config['region'];
    		$instanceSystem		=	$System;
    		$instanceOS         =   $OS;
    
    		$nagiosInstances[] = [
    			'use'       =>  $instanceOS,
        		'name'      =>  $instanceName,
        		'IP'        =>  $instancePublicIP,
        		'id'        =>  $instanceId,
        		'services'  =>  (isset($instanceServices) ? $instanceServices : ''),
    			'region'    =>  $instanceRegion,
    			'system'	=>  $System
    		];
		}
	}
}

/**
 * Generate nagios3 file
 */
if ($nagiosInstances <> '')
{
	$outputHosts = '';
	$outputServices = '';
	$outputHostgroups = '';
	$hostgroupsFull = '';

	$hostTemplate = file_get_contents('templates/my.template');
	foreach ($nagiosInstances as $nagiosInstance)
	{
    	/**
     	* I like working with objects :-)
     	*/
    	$instance = (object) $nagiosInstance;

    	$tmp = $hostTemplate;
    	$tmp = str_replace('{nagiosServerType}', $instance->use, $tmp);
    	$tmp = str_replace('{Hostname}', $instance->name, $tmp);
    	$tmp = str_replace('{InstanceId}', $instance->id, $tmp);
    	$tmp = str_replace('{IP}', $instance->IP, $tmp);
    	$tmp = str_replace('{Region}', $instance->region, $tmp);
    	$tmp = str_replace('{System}', $instance->system, $tmp);

    	$outputHosts .= $tmp."\n\n";

    	/**
     	* Generate services
     	*/
    	if ($instance->system != '')
    	{
        	$services = explode('|', $instance->system);

        	foreach ($services as $service)
        	{
            	$serviceTemplate = file_get_contents('templates/service.my.template');
            	$serviceTemplate = str_replace('{hostname}', $instance->name, $serviceTemplate);
            	$outputServices .= $serviceTemplate."\n\n";
        	}

    	}
    	if ($instance->system != '')
    	{
    		$hostgroupsTmp = explode('|', $instance->system);
    		$hostgroupsFull .= $hostgroupsFull." ".$hostgroupsTmp[0];
    	}

	}

	$hostgroups = explode(" ", $hostgroupsFull);
	$hostgroups = array_unique($hostgroups);
	foreach ($hostgroups as $hostgroup)
	{
		if ($hostgroup != '')
		{
			$hostgroupTemplate = file_get_contents('templates/hostgroup.template');
			$hostgroupTemplate = str_replace('{System}', $hostgroup, $hostgroupTemplate);
			$outputHostgroups .= $hostgroupTemplate."\n\n";
		}
	
	}

	$instanceFile=$region.'_instances.cfg';
	file_put_contents($instanceFile, $outputHosts);
	$hostgroupFile='hostgroups.cfg';
	if (file_exists($hostgroupFile))
	{
		$outputHostgroups .= file_get_contents($hostgroupFile);
	}
	file_put_contents($hostgroupFile, $outputHostgroups);
}

?>
