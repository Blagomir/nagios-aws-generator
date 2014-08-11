<?php

/**
 * Require composer autoload class
 */
require 'vendor/autoload.php';


$AWSKey = getenv('AWS_KEY');
$AWSSecret = getenv('AWS_SECRET');


$config = array();
$config['key'] = $AWSKey;
$config['secret'] = $AWSSecret;
$config['region'] = 'eu-west-1';
$ec2Client = \Aws\Ec2\Ec2Client::factory($config);


$result = $ec2Client->DescribeInstances(array(
    'DryRun' => false
));



/**
 * Instances container
 */
$instances = [];

/**
 * Loop over results from AWS API
 */
foreach ($result['Reservations'] as $k => $v)
{

    $instanceServices = '';

    /**
     * Get instance name
     */
    foreach ($v['Instances'][0]['Tags'] as $key => $val)
    {
        if ($val['Key'] == 'Name')
        {
            $instanceName = $val['Value'];
        }
        elseif ($val['Key'] == 'nagios')
        {
            $instanceServices = $val['Value'];
        }
    }

    /**
     * Get instance information
     */
    $instancePublicIP   =   $v['Instances'][0]['PublicIpAddress'];
    $instanceId         =   $v['Instances'][0]['InstanceId'];

    $instances[] = [
        'name'      =>  $instanceName,
        'IP'        =>  $instancePublicIP,
        'id'        =>  $instanceId,
        'services'  =>  (isset($instanceServices) ? $instanceServices : '')
    ];
}

/**
 * Generate nagios3 file
 */
$outputHosts = '';
$outputServices = '';

$hostTemplate = file_get_contents('templates/host.template');

foreach ($instances as $k => $v)
{
    /**
     * I like working with objects :-)
     */
    $instance = (object) $v;

    $tmp = $hostTemplate;
    $tmp = str_replace('{hostname}', $instance->name, $tmp);
    $tmp = str_replace('{alias}', $instance->id, $tmp);
    $tmp = str_replace('{IP}', $instance->IP, $tmp);

    $outputHosts .= $tmp."\n\n";

    /**
     * Generate services
     */
    if ($instance->services != '')
    {
        $services = explode('|', $instance->services);

        foreach ($services as $service)
        {
            $serviceTemplate = file_get_contents('templates/service.'.$service.'.template');
            $serviceTemplate = str_replace('{hostname}', $instance->name, $serviceTemplate);
            $outputServices = $serviceTemplate."\n\n";
        }

    }

}

echo $outputHosts;
echo $outputServices;






?>