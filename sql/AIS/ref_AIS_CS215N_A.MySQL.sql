-- MySQL Table Structure USPS AIS CS215N
-- This database table contains the data generated from product AIS CS215N
-- Record Type: [A]lias
--
-- Author: Ben Dauphinee <ben.dauphinee@cpap.com>
-- GitHub: https://github.com/cpapdotcom/USPSTools
-- Version: 1.0
-- License: MIT

CREATE TABLE `ref_AIS_CS215N_A` (
  `CopyrightCode` char(1) DEFAULT NULL,
  `ZIP` int(11) DEFAULT NULL,
  `AliasStreetAbbreviation` varchar(2) DEFAULT NULL,
  `AliasStreetName` varchar(28) DEFAULT NULL,
  `AliasStreetSuffixAbbreviation` varchar(4) DEFAULT NULL,
  `AliasStreetPostDirectionalAbbreviation` varchar(2) DEFAULT NULL,
  `StreetPreDirectionalAbbreviation` varchar(2) DEFAULT NULL,
  `StreetName` varchar(28) DEFAULT NULL,
  `StreetSuffixAbbreviation` varchar(4) DEFAULT NULL,
  `StreetPostDirectionalAbbreviation` varchar(2) DEFAULT NULL,
  `AliasTypeCode` char(1) DEFAULT NULL,
  `AliasCentury` varchar(2) DEFAULT NULL,
  `AliasYear` varchar(2) DEFAULT NULL,
  `AliasMonth` varchar(2) DEFAULT NULL,
  `AliasDay` varchar(2) DEFAULT NULL,
  `AliasDeliveryAddressLow` varchar(10) DEFAULT NULL,
  `AliasDeliveryAddressHigh` varchar(10) DEFAULT NULL,
  `AliasRangeCode` char(1) DEFAULT NULL,
  `Filler` varchar(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;