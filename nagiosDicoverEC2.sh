#!/bin/bash
source nagiosDicover.properties

export AWS_KEY=$awsKey
export AWS_SECRET=$awsSecret

getEc2Regions () {
	aws ec2 describe-regions --output text |awk '{print $NF}' | sort
}

getEc2RegionName () {
	region=$1
	case $region in
		us-east-1)
			echo "US East (N. Virginia)"
			;;
		us-west-2)
			echo "US West (Oregon)"
			;;
		us-west-1)
			echo "US West (N. California)"
			;;
		eu-west-1)
			echo "EU (Ireland)"
			;;
		eu-central-1)
			echo "EU (Frankfurt)"
			;;
		ap-southeast-1)
			echo "Asia Pacific (Singapore)"
			;;
		ap-northeast-1)
			echo "Asia Pacific (Tokyo)"
			;;
		ap-northeast-2)
			echo "Asia Pacific (Seoul)"
			;;
		ap-southeast-2)
			echo "Asia Pacific (Sydney)"
			;;
		sa-east-1)
			echo "South America (Sao Paulo)"
			;;
		ap-south-1)
			echo "Asia Pacific (Mumbai)"
			;;
		ca-central-1)
			echo "Canada (Central)"
			;;
		us-east-2)
			echo "US East (Ohio)"
			;;
		*)
			echo $region " is unknown region"
			exit 1
	esac
}

now=`date +"%Y%m%d"`
outputPath=output/$now
mkdir -p $outputPath
yesterday=`echo "$now-1" | bc`
yesterdayPath=output/$yesterday
\rm -f $outputPath/*.cfg
\rm -f *.cfg

for region in `getEc2Regions` ; do 
	regionName=`getEc2RegionName $region`
	echo -n $regionName": "
	/usr/bin/php generate.php $region
	if [ "$?" = "0" ]; then
		echo "All OK"
		fileName=`awk -F\( '{print $2}' <<< $regionName | sed 's/)//' | sed 's/ //' | sed 's/N./North/'`_instances.cfg
		mv $fileName $outputPath
	else
		echo "Found Errors"			
	fi
done
mv hostgroups.cfg $outputPath
dos2unix -q $outputPath/*.cfg

# upload to Nagios server
ssh $nagiosServeIP 'mkdir -p /etc/nagios/old/$yesterday && mv /etc/nagios/all/*.cfg /etc/nagios/old/$yesterday'
scp $outputPath/* $nagiosServeIP:/etc/nagios/all/
ssh $nagiosServeIP 'cd /etc/nagios/ ; /usr/bin/nagios -v /etc/nagios/nagios.cfg && service nagios restart'