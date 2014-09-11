# Description


Simple PHP library which will help you generate hosts and services configuration files for nagios3 based on your AWS setup.


# How to use

 In order to use this, you need to export two variables and run the console command below.

```bash
 export AWS_KEY=YOUR_AWS_ACCESS_KEY

 export AWS_SECRET=YOUR_AWS_ACCESS_KEY_SECRET
````


 Run this the console:

```bash
 php generate.php
````


 The generator will find all of your instances which have tag "nagios" no matter what the value is.

 The proper value could be "check_load" or if you wish to have more than one service checked on that instance add "check_load|check_root_partition" (divide services with pipe "|")

# Templates

You need template file matching the service name you want to execute.

For "check_load" service you need "templates/service.check_load.template"

 You can create template files based on your setup.
