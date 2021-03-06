# rrdcalendar

![display calendar format graph](https://gyazo.com/dcbb19d5464fa755d42b5c62df6c1b81/raw)


## Overview

This cacti plugin provides the ability to display graphs on the system in calendar format.


## Requirement

This plugin requires ImageMagick package.


## Installation

```
cd /usr/share/cacti/plugins (or installed cacti directory)
git clone https://github.com/bashaway/rrdcalendar

chown apache.apache ./rrdcalendar/cache

dnf -y install ImageMagick
```

Install Plugins  
https://github.com/Cacti/documentation/blob/develop/Plugins.md



## Configuration

### Global settings

Console -> Configuration -> Settings -> RRDcalendar

![Config Global Settings](https://gyazo.com/f90c4ffd4feef3a9e3eb5cd76a7ff9d3/raw)

* RRDTool Command Path : set rrdtool path.(default:/usr/bin/rrdtool)
* convert Command Path : set convert path.(default:/usr/bin/convert)
* writable image directory : set image cache path.(default:rrdcalendar/cache)


### User settings

Console -> Configuration -> Users -> (select user) -> User Settings 

![Configu User Settins](https://gyazo.com/f55e781926215105e663d271f03b003d/raw)

* Display Legend : Display legends below graph.
* Start Day of Week : choose beginning of the week to be on Sunday or Monday.
* Fontsize : select graph size.
* Custom Title : input title format.


## ChangeLog

--- 0.5 ---

* Initial public release

<!-- git add -A ; git commit -m "COMIT COMMENT" ;  git push -u origin master -->
