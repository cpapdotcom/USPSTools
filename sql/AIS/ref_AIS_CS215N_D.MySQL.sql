-- MySQL Table Structure USPS AIS CS215N
-- This database table contains the data generated from product AIS CS215N
-- Record Type: [D]etail

CREATE TABLE `ref_AIS_CS215N` (
  `CopyrightCode` char(1) DEFAULT NULL,
  `Zip` int(11) DEFAULT NULL,
  `CityStateKey` varchar(6) DEFAULT NULL,
  `ZipClassificationCode` char(1) DEFAULT NULL,
  `CityStateName` varchar(28) DEFAULT NULL,
  `CityStateNameAbbreviation` char(13) DEFAULT NULL,
  `CityStateNameFacilityCode` char(1) DEFAULT NULL,
  `CityStateNameMailingNameIndicator` char(1) DEFAULT NULL,
  `PreferredLastLineCityStateKey` varchar(6) DEFAULT NULL,
  `PreferredLastLineCityStateName` varchar(28) DEFAULT NULL,
  `CityDeliveryIndicator` char(1) DEFAULT NULL,
  `CarrierRouteRateSortation` varchar(1) DEFAULT NULL,
  `ZipNameIndicator` char(1) DEFAULT NULL,
  `FinanceNumber` int(11) DEFAULT NULL,
  `StateAbbreviation` varchar(2) DEFAULT NULL,
  `CountyNumber` varchar(3) DEFAULT NULL,
  `CountyName` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;