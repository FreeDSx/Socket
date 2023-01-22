CHANGELOG
=========

0.6.1 (2023-01-21)
------------------
* Slight optimization for Socket::isConnected when not using a unix based socket.

0.6.0 (2023-01-21)
------------------
* Fix SSL based option mappings (ssl_crypto_type).
* Fix how Socket::isConnected is determined. The feof behavior was unreliable in PHP 8.2.

0.5.2 (2021-12-31)
------------------
* Support constructing a socket server from a unix socket.

0.5.1 (2021-12-29)
------------------
* Support constructing a socket pool from a unix socket.

0.5.0 (2021-12-29)
------------------
* Support creating unix based sockets with the client.

0.4.1 (2021-12-11)
------------------
* Support PHP 8.0 / 8.1.

0.4.0 (2019-12-14)
------------------
* Change how the MessageQueue handles buffered data. It now supports an optional MessageWrapper.

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
