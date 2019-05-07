#!/bin/sh

case "$1" in

  start)
        echo -n "Starting LOKINET daemon\n"
        lokinet > /dev/null 2>&1 &
        ;;

  connect)
        ehco -n "rerouted iptables\n"
        sudo ip rule add from 10.3.141.1 lookup main prio 1000
        echo -n "added wlan0 address rule\n"
        sudo ip rule add from 10.3.141.0/24 lookup lokinet prio 1000
        echo -n "added wifi-clients rule\n"
        sleep 3
        sudo ip route add default dev lokitun0 table lokinet
        echo -n "added lokitun0 route\n"
        echo -n "Restarting DNSMASQ\n"
        ;;

  stop)
        echo -n "Stopping LOKINET daemon\n"
        pkill lokinet
        ;;

disconnect)
        sudo ip rule del from 10.3.141.1 lookup main prio 1000 #LOKIPAP
        echo -n "removed wlan0 address rule\n"
        sudo ip rule del from 10.3.141.0/24 lookup lokinet prio 1000 #LOKIPAP
        echo -n "removed wifi-clients rule\n"
        sudo ip route del default dev lokitun0 table lokinet
        echo -n "removed lokitun0 route\n"
        echo -n "Lokinet terminated - Network encryption services ended\n"
        ;;

  gen)
        echo -n "NEW lokinet.ini FILE CREATED\n"
        lokinet "-g"
        cp /root/.lokinet/lokinet.ini /usr/local/bin/
        cat /usr/local/bin/lokinet.ini
        ;;

bootstrap)
        echo -n "LOKINET DICONNECTED AND DAEMON SHUTDOWN FOR BOOTSTRAPPING\n"
        pkill lokinet
        sleep 2
        pidof lokinet >/dev/null && echo "Service is running\n" || echo "Service NOT running\n"
        echo -n "FETCH BOOTSTRAP <---- "
        lokinet-bootstrap "$2"
        echo -n "SUCCESS! BOOTSTRAPPED WTIH ---> $2\n\n"
        echo -n "YOU MUST MANUALLY RESTART LOKINET DAEMON AND RECONNECT FOR SERVICE\n"
        ;;

  *)
        echo "Usage: "$1" {start|stop|gen|bootstrap|connect|disconnect}"
        exit 1
        ;;
        esac
