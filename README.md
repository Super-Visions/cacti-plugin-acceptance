# Acceptance

Approve and deploy devices

## Purpose

On large installation it is useful to have a separate Cacti instance where you can test and develop new devices and host-profiles, and when ready, you want the devices to be deployed to the _production_ server. Acceptance plugin shows a page with a list of devices that are to be verified by an operator. You can click trough on links to see a list of graphs and a list of data sources, there is also a link to see graphs in _preview view_ and a link to edit host details.

## Features

  * Acceptance page with some information like number of graphs/data sources, host template, hostname and host status.
  * Filter devices on Acceptance report page.
  * 1-click approve and deploy devices to production server.
  * Ignore devices ( = set Enabled to false ).
  * Optional: Remove data sources linked to indexes that don't exist anymore (on re-index).
  * Optional: Remove data sources that seem to be duplicates from existing data sources (on re-index).
  * Optional: Re-run data queries that have "Uptime Goes Backwards" as re-index method at specified interval.

## Prerequisites

This plugin is tested with Cacti 0.8.8a and PIA 3.1 but should also work on older versions.
HTTP access from dev/acc server to production server is required.

## Installation

Untar contents to the Cacti plugins directory and install/activate from Cacti UI on both dev and prod server.
On the prod server you will also need to change the IP address on line 5 of the [.htaccess](.htaccess) file in order to let your dev/acc server contact the prod server to add new devices.
