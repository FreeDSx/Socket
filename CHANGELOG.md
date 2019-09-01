CHANGELOG
=========

0.3.1 (2019-09-01)
------------------
* Fix the isConnected method to determine whether or not the stream is open.
* More strict tests / checks / handling in different spots.
* Add PHPStan to CI runs at level 6.

0.3.0 (2019-03-03)
------------------
* Update the ASN.1 Message Queue to use the last position of the encoder.
* Change 'trailing data' to 'last position' for the messages.

0.2.2 (2019-02-25)
------------------
* Fix an incorrect SSL context option name (cafile).

0.2.1 (2019-01-21)
------------------
* Minor performance adjustments.

0.2.0 (2018-07-29)
------------------
* Add UDP client / server functionality.
* Remove the ASN.1 library dependency.
* Add a buffer size option.

0.1.0 (2018-05-06)
------------------
* Initial release.
