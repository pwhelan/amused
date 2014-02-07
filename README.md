Amused
======

I am not amused! ... So I set out to make a server to handle my music for me.

Amused aims to be a multi-service server that scans, re-encodes and syncs music between several servers and clients. At it's core it uses ReactPHP to provide said services.

At the moment this is at best a Proof of Concept for what you can do with ReactPHP, at worst it's a demonstration of what you should not do (ie: invoke a bunch of packages from packagist without considering whether or not they are sync or async).

Prerequisites
-------------

Amused requires several services to store data as well as to communicate.

  * Redis - key/value store database, used as a task queue.
  * MySQL - relational database, used to store data.

It also requires several PHP PECL extensions.

  * Inotify - inode notifications, used to watch for changes in music files/directories.
  * Phpiredis - high speed API for redis.

Recommended
-----------

ReactPHP works best with libev or libevent, so get that if you can.

Installation
------------

Once the base services and extensions are installed it's time to get Amused up and running. Simply check out a copy from Github and run composer to get the dependencies:

    user@host:~Code$ git clone https://github.com/pwhelan/amused.git
    user@host:~Code$ cd amused
    user@host:~Code/amused$ composer install

Now that you have it fully installed it is time to configure it. For the momment there are no fancy configuration files so go ahead and search for all instances of the \React\Mysql\Connection class and change the parameters to match your database configuration. Do the same for the phinx.yml file, then execute phinx to create the database schema:

    user@host:~Code/amused$ ./vendor/bin/phinx migrate

Now you should have a fully working instance of Amused, prepare to be ~Amased~.
  
Execution
---------

To start up Amused all one needs to do is use the amused script:

    user@host:~Code/amused$ ./bin/amused

To get it to scan a directory use the amuse command line script:

    user@host:~Code/amused$ ./bin/amuse ~/Music

Now all the services will start talking to each other, scanning all the files, searching for those that have music, getting their tags and saving them in the database.
