PHP-ZFSHealthCheck
==============
**PHP-ZFSHealthCheck** is as the name suggests a few health check scripts written in PHP to check if your node's zpool and disks are still healthy and have enough space left on them.

Each script can be run individually or all together using the checkAllWrapper. The way to communicate a failure is handled in the FailHandler. for now this is only via Email and/or Syslog. but feel free to add different methods of communication.

### Disclamer
The point of these scripts where to simply check (standalone) some things without a bigass codebase behind it. i know its a bit Mickey Mouse :)

###Confirmed Operating Systems
this code has been tested and confirmed working on:

* FreeBSD 9.3 

Requirements
============
* PHP 5.4 or higher. not tested on lower versions
* an Zpool to monitor
* smartmontools installed, if you want to check your diskhealth 

Usage
============
Set a cronjob to run the desired checks
````
0 12 * * * /bin/php /usr/local/bin/CheckAllWrapper.php > /var/log/ZfsCheckCron.log 2>&1
````
License
===============
    Copyright (c) 2014 seoSepa

    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

