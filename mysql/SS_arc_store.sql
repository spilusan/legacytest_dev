-- MySQL dump 10.11
--
-- Host: localhost    Database: SS_arc_store
-- ------------------------------------------------------
-- Server version	5.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `arc_tests_g2t`
--

DROP TABLE IF EXISTS `arc_tests_g2t`;
CREATE TABLE `arc_tests_g2t` (
  `g` mediumint(8) unsigned NOT NULL,
  `t` mediumint(8) unsigned NOT NULL,
  UNIQUE KEY `gt` (`g`,`t`),
  KEY `tg` (`t`,`g`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci DELAY_KEY_WRITE=1;

--
-- Dumping data for table `arc_tests_g2t`
--

LOCK TABLES `arc_tests_g2t` WRITE;
/*!40000 ALTER TABLE `arc_tests_g2t` DISABLE KEYS */;
INSERT INTO `arc_tests_g2t` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(1,45),(1,46),(1,47),(1,48),(1,49),(1,50),(1,51),(1,52),(1,53),(1,54),(1,55),(1,56),(1,57),(1,58),(1,59),(1,60),(1,61),(1,62),(1,63),(1,64),(1,65),(1,66),(1,67),(1,68),(1,69),(1,70),(1,71),(1,72),(1,73),(1,74),(1,75),(1,76),(1,77),(1,78),(1,79),(1,80),(1,81),(1,82),(1,83),(1,84),(1,85),(1,86),(1,87),(1,88),(1,89),(1,90),(1,91),(1,92),(1,93),(1,94),(1,95),(1,96),(1,97),(1,98),(1,99),(1,100),(1,101),(1,102),(1,103),(1,104),(1,105),(1,106),(1,107),(1,108),(1,109),(1,110),(1,111),(1,112),(1,113),(1,114),(1,115),(1,116),(1,117),(1,118),(1,119),(1,120),(1,121),(1,122),(1,123),(1,124),(1,125),(1,126),(1,127),(1,128),(1,129),(1,130),(1,131),(1,132),(1,133),(1,134),(1,135),(1,136),(1,137),(1,138),(1,139),(1,140),(1,141),(1,142),(1,143),(1,144),(1,145),(1,146),(1,147),(1,148),(1,149),(1,150),(1,151),(1,152),(1,153),(1,154),(1,155),(1,156),(1,157),(1,158),(1,159),(1,160),(1,161),(1,162),(1,163),(1,164),(1,165),(1,166),(1,167),(1,168),(1,169),(1,170),(1,171),(1,172),(1,173),(1,174),(1,175),(1,176),(1,177),(1,178),(1,179),(1,180),(1,181),(1,182),(1,183),(1,184),(1,185),(1,186),(1,187),(1,188),(1,189),(1,190),(1,191),(1,192),(1,193),(1,194),(1,195),(1,196),(1,197),(1,198),(1,199),(1,200),(1,201),(1,202),(1,203),(1,204),(1,205),(1,206),(1,207),(1,208),(1,209),(1,210),(1,211),(1,212),(1,213),(1,214),(1,215),(1,216),(1,217),(1,218),(1,219);
/*!40000 ALTER TABLE `arc_tests_g2t` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arc_tests_id2val`
--

DROP TABLE IF EXISTS `arc_tests_id2val`;
CREATE TABLE `arc_tests_id2val` (
  `id` mediumint(8) unsigned NOT NULL,
  `misc` tinyint(1) NOT NULL default '0',
  `val` text collate utf8_unicode_ci NOT NULL,
  `val_type` tinyint(1) NOT NULL default '0',
  UNIQUE KEY `id` (`id`,`val_type`),
  KEY `v` (`val`(64))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci DELAY_KEY_WRITE=1;

--
-- Dumping data for table `arc_tests_id2val`
--

LOCK TABLES `arc_tests_id2val` WRITE;
/*!40000 ALTER TABLE `arc_tests_id2val` DISABLE KEYS */;
INSERT INTO `arc_tests_id2val` VALUES (1,0,'http://rdf.myshipserv.com/ontology.rdf',0),(3,0,'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',0),(5,0,'',2),(6,0,'http://purl.org/dc/elements/1.1/title',0),(9,0,'http://rdf.myshipserv.com/schema/zone',0),(18,0,'http://rdf.myshipserv.com/schema/content',0),(55,0,'http://rdf.myshipserv.com/schema/productattributetype',0),(71,0,'http://rdf.myshipserv.com/schema/synonymOf',0);
/*!40000 ALTER TABLE `arc_tests_id2val` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arc_tests_o2val`
--

DROP TABLE IF EXISTS `arc_tests_o2val`;
CREATE TABLE `arc_tests_o2val` (
  `id` mediumint(8) unsigned NOT NULL,
  `misc` tinyint(1) NOT NULL default '0',
  `val` text collate utf8_unicode_ci NOT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `v` (`val`(64))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci DELAY_KEY_WRITE=1;

--
-- Dumping data for table `arc_tests_o2val`
--

LOCK TABLES `arc_tests_o2val` WRITE;
/*!40000 ALTER TABLE `arc_tests_o2val` DISABLE KEYS */;
INSERT INTO `arc_tests_o2val` VALUES (4,0,'http://rdf.myshipserv.com/schema/brand'),(7,0,'Alfa Laval'),(10,0,'http://rdf.myshipserv.com/ontology.rdf#lifeboats'),(11,0,'Viking'),(13,0,'http://rdf.myshipserv.com/ontology.rdf#gmdss'),(14,0,'McMurdo'),(16,0,'ACR Electronics'),(9,0,'http://rdf.myshipserv.com/schema/zone'),(17,0,'GMDSS'),(19,0,'gmdss.xml'),(20,0,'Lifeboats'),(21,0,'lifeboats.xml'),(23,0,'Chandlers'),(24,0,'chandlers.xml'),(26,0,'http://rdf.myshipserv.com/schema/supplierspecialisation'),(22,0,'http://rdf.myshipserv.com/ontology.rdf#chandlers'),(27,0,'Provisions'),(29,0,'Gas Supplies'),(31,0,'Safety Equipment'),(33,0,'Bonded Stores'),(35,0,'Welding'),(37,0,'Pyrotechnics'),(39,0,'http://rdf.myshipserv.com/schema/suppliercertification'),(40,0,'ISSA Member'),(42,0,'ISSA Quality'),(44,0,'ISO 9001'),(46,0,'http://rdf.myshipserv.com/schema/producttype'),(47,0,'EPIRB'),(49,0,'NAVTEX'),(51,0,'Inmarsat'),(53,0,'Davits'),(55,0,'http://rdf.myshipserv.com/schema/productattributetype'),(56,0,'Sea Area'),(58,0,'http://rdf.myshipserv.com/schema/productattribute'),(54,0,'http://rdf.myshipserv.com/ontology.rdf#seaarea'),(59,0,'A1'),(61,0,'A2'),(63,0,'A3'),(65,0,'A4'),(67,0,'http://rdf.myshipserv.com/schema/keyword'),(68,0,'chandlery'),(70,0,'http://rdf.myshipserv.com/schema/synonym'),(66,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-chandlery'),(72,0,'chandler'),(74,0,'shipchandler'),(76,0,'stores'),(78,0,'#keyword-chandlery'),(79,0,'chandlers'),(81,0,'shipchandlers'),(83,0,'ship_chander'),(85,0,'ship_chandlers'),(87,0,'chandlier'),(89,0,'chandliers'),(91,0,'shipchandlier'),(93,0,'shipchandliers'),(95,0,'chandlary'),(97,0,'chand'),(99,0,'shipschandlers'),(101,0,'chandlering'),(103,0,'shipchandlering'),(105,0,'chandlrey'),(107,0,'chandlrer'),(109,0,'chandlrers'),(111,0,'shipchandlrey'),(113,0,'shipchandlrer'),(115,0,'shipchandlrers'),(117,0,'chandle'),(119,0,'shipchandle'),(122,0,'ISSA'),(123,0,'issa.xml'),(125,0,'Hamburg'),(126,0,'hamburg.xml'),(128,0,'Rotterdam'),(129,0,'rotterdam.xml'),(131,0,'Shanghai'),(132,0,'shanghai.xml'),(134,0,'Singapore'),(135,0,'singapore.xml'),(124,0,'http://rdf.myshipserv.com/ontology.rdf#hamburg'),(137,0,'hamburg'),(127,0,'http://rdf.myshipserv.com/ontology.rdf#rotterdam'),(139,0,'rotterdam'),(130,0,'http://rdf.myshipserv.com/ontology.rdf#shanghai'),(141,0,'shanghai'),(133,0,'http://rdf.myshipserv.com/ontology.rdf#singapore'),(143,0,'singapore'),(136,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-hamburg'),(138,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-rotterdam'),(140,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-shanghai'),(142,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-singapore'),(121,0,'http://rdf.myshipserv.com/ontology.rdf#issa'),(149,0,'issa'),(148,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-issa'),(152,0,'germany'),(154,0,'netherlands'),(156,0,'china'),(158,0,'Copenhagen'),(159,0,'copenhagen.xml'),(157,0,'http://rdf.myshipserv.com/ontology.rdf#copenhagen'),(161,0,'copenhagen'),(160,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-copenhagen'),(164,0,'denmark');
/*!40000 ALTER TABLE `arc_tests_o2val` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arc_tests_s2val`
--

DROP TABLE IF EXISTS `arc_tests_s2val`;
CREATE TABLE `arc_tests_s2val` (
  `id` mediumint(8) unsigned NOT NULL,
  `misc` tinyint(1) NOT NULL default '0',
  `val` text collate utf8_unicode_ci NOT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `v` (`val`(64))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci DELAY_KEY_WRITE=1;

--
-- Dumping data for table `arc_tests_s2val`
--

LOCK TABLES `arc_tests_s2val` WRITE;
/*!40000 ALTER TABLE `arc_tests_s2val` DISABLE KEYS */;
INSERT INTO `arc_tests_s2val` VALUES (2,0,'http://rdf.myshipserv.com/ontology.rdf#alfalaval'),(8,0,'http://rdf.myshipserv.com/ontology.rdf#viking'),(12,0,'http://rdf.myshipserv.com/ontology.rdf#mcmurdo'),(15,0,'http://rdf.myshipserv.com/ontology.rdf#arcelectronics'),(13,0,'http://rdf.myshipserv.com/ontology.rdf#gmdss'),(10,0,'http://rdf.myshipserv.com/ontology.rdf#lifeboats'),(22,0,'http://rdf.myshipserv.com/ontology.rdf#chandlers'),(25,0,'http://rdf.myshipserv.com/ontology.rdf#provisions'),(28,0,'http://rdf.myshipserv.com/ontology.rdf#gassupplies'),(30,0,'http://rdf.myshipserv.com/ontology.rdf#safetyequipment'),(32,0,'http://rdf.myshipserv.com/ontology.rdf#bondedstores'),(34,0,'http://rdf.myshipserv.com/ontology.rdf#welding'),(36,0,'http://rdf.myshipserv.com/ontology.rdf#pyrotechnics'),(38,0,'http://rdf.myshipserv.com/ontology.rdf#issamember'),(41,0,'http://rdf.myshipserv.com/ontology.rdf#issaquality'),(43,0,'http://rdf.myshipserv.com/ontology.rdf#iso9001'),(45,0,'http://rdf.myshipserv.com/ontology.rdf#epirb'),(48,0,'http://rdf.myshipserv.com/ontology.rdf#navtex'),(50,0,'http://rdf.myshipserv.com/ontology.rdf#inmarsat'),(52,0,'http://rdf.myshipserv.com/ontology.rdf#davits'),(54,0,'http://rdf.myshipserv.com/ontology.rdf#seaarea'),(57,0,'http://rdf.myshipserv.com/ontology.rdf#seaareaA1'),(60,0,'http://rdf.myshipserv.com/ontology.rdf#seaareaA2'),(62,0,'http://rdf.myshipserv.com/ontology.rdf#seaareaA3'),(64,0,'http://rdf.myshipserv.com/ontology.rdf#seaareaA4'),(66,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-chandlery'),(69,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandler'),(73,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandler'),(75,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-stores'),(77,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlers'),(80,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandlers'),(82,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-ship_chander'),(84,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-ship-chandlers'),(86,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlier'),(88,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandliers'),(90,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandlier'),(92,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandliers'),(94,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlary'),(96,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chand'),(98,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipschandlers'),(100,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlering'),(102,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandlering'),(104,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlrey'),(106,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlrer'),(108,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlrers'),(110,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandlrey'),(112,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandlrer'),(114,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandlrers'),(116,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandle'),(118,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shipchandle'),(120,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-chandlery'),(121,0,'http://rdf.myshipserv.com/ontology.rdf#issa'),(124,0,'http://rdf.myshipserv.com/ontology.rdf#hamburg'),(127,0,'http://rdf.myshipserv.com/ontology.rdf#rotterdam'),(130,0,'http://rdf.myshipserv.com/ontology.rdf#shanghai'),(133,0,'http://rdf.myshipserv.com/ontology.rdf#singapore'),(136,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-hamburg'),(138,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-rotterdam'),(140,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-shanghai'),(142,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-singapore'),(144,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-hamburg'),(145,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-rotterdam'),(146,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-shanghai'),(147,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-singapore'),(148,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-issa'),(150,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-issa'),(151,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-germany'),(153,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-netherlands'),(155,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-china'),(157,0,'http://rdf.myshipserv.com/ontology.rdf#copenhagen'),(160,0,'http://rdf.myshipserv.com/ontology.rdf#keyword-copenhagen'),(162,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-copenhagen'),(163,0,'http://rdf.myshipserv.com/ontology.rdf#synonym-denmark');
/*!40000 ALTER TABLE `arc_tests_s2val` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arc_tests_setting`
--

DROP TABLE IF EXISTS `arc_tests_setting`;
CREATE TABLE `arc_tests_setting` (
  `k` char(32) collate utf8_unicode_ci NOT NULL,
  `val` text collate utf8_unicode_ci NOT NULL,
  UNIQUE KEY `k` (`k`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci DELAY_KEY_WRITE=1;

--
-- Dumping data for table `arc_tests_setting`
--

LOCK TABLES `arc_tests_setting` WRITE;
/*!40000 ALTER TABLE `arc_tests_setting` DISABLE KEYS */;
/*!40000 ALTER TABLE `arc_tests_setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arc_tests_triple`
--

DROP TABLE IF EXISTS `arc_tests_triple`;
CREATE TABLE `arc_tests_triple` (
  `t` mediumint(8) unsigned NOT NULL,
  `s` mediumint(8) unsigned NOT NULL,
  `p` mediumint(8) unsigned NOT NULL,
  `o` mediumint(8) unsigned NOT NULL,
  `o_lang_dt` mediumint(8) unsigned NOT NULL,
  `o_comp` char(35) collate utf8_unicode_ci NOT NULL,
  `s_type` tinyint(1) NOT NULL default '0',
  `o_type` tinyint(1) NOT NULL default '0',
  `misc` tinyint(1) NOT NULL default '0',
  UNIQUE KEY `t` (`t`),
  KEY `sp` (`s`,`p`),
  KEY `os` (`o`,`s`),
  KEY `po` (`p`,`o`),
  KEY `misc` (`misc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci DELAY_KEY_WRITE=1;

--
-- Dumping data for table `arc_tests_triple`
--

LOCK TABLES `arc_tests_triple` WRITE;
/*!40000 ALTER TABLE `arc_tests_triple` DISABLE KEYS */;
INSERT INTO `arc_tests_triple` VALUES (1,2,3,4,5,'http-rdf-myshipserv-com-schema-bran',0,0,0),(2,2,6,7,5,'Alfa-Laval',0,2,0),(3,8,3,4,5,'http-rdf-myshipserv-com-schema-bran',0,0,0),(4,8,9,10,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(5,8,6,11,5,'Viking',0,2,0),(6,12,3,4,5,'http-rdf-myshipserv-com-schema-bran',0,0,0),(7,12,9,13,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(8,12,6,14,5,'McMurdo',0,2,0),(9,15,3,4,5,'http-rdf-myshipserv-com-schema-bran',0,0,0),(10,15,9,13,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(11,15,6,16,5,'ACR-Electronics',0,2,0),(12,13,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(13,13,6,17,5,'GMDSS',0,2,0),(14,13,18,19,5,'gmdss-xml',0,2,0),(15,10,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(16,10,6,20,5,'Lifeboats',0,2,0),(17,10,18,21,5,'lifeboats-xml',0,2,0),(18,22,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(19,22,6,23,5,'Chandlers',0,2,0),(20,22,18,24,5,'chandlers-xml',0,2,0),(21,25,3,26,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(22,25,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(23,25,6,27,5,'Provisions',0,2,0),(24,28,3,26,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(25,28,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(26,28,6,29,5,'Gas-Supplies',0,2,0),(27,30,3,26,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(28,30,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(29,30,6,31,5,'Safety-Equipment',0,2,0),(30,32,3,26,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(31,32,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(32,32,6,33,5,'Bonded-Stores',0,2,0),(33,34,3,26,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(34,34,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(35,34,6,35,5,'Welding',0,2,0),(36,36,3,26,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(37,36,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(38,36,6,37,5,'Pyrotechnics',0,2,0),(39,38,3,39,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(40,38,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(41,38,6,40,5,'ISSA-Member',0,2,0),(42,41,3,39,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(43,41,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(44,41,6,42,5,'ISSA-Quality',0,2,0),(45,43,3,39,5,'http-rdf-myshipserv-com-schema-supp',0,0,0),(46,43,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(47,43,6,44,5,'ISO-9001',0,2,0),(48,45,3,46,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(49,45,9,13,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(50,45,6,47,5,'EPIRB',0,2,0),(51,48,3,46,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(52,48,9,13,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(53,48,6,49,5,'NAVTEX',0,2,0),(54,50,3,46,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(55,50,9,13,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(56,50,6,51,5,'Inmarsat',0,2,0),(57,52,3,46,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(58,52,9,10,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(59,52,6,53,5,'Davits',0,2,0),(60,54,3,55,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(61,54,9,13,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(62,54,6,56,5,'Sea-Area',0,2,0),(63,57,3,58,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(64,57,55,54,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(65,57,6,59,5,'A1',0,2,0),(66,60,3,58,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(67,60,55,54,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(68,60,6,61,5,'A2',0,2,0),(69,62,3,58,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(70,62,55,54,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(71,62,6,63,5,'A3',0,2,0),(72,64,3,58,5,'http-rdf-myshipserv-com-schema-prod',0,0,0),(73,64,55,54,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(74,64,6,65,5,'A4',0,2,0),(75,66,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(76,66,9,22,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(77,66,6,68,5,'chandlery',0,2,0),(78,69,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(79,69,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(80,69,6,72,5,'chandler',0,2,0),(81,73,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(82,73,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(83,73,6,74,5,'shipchandler',0,2,0),(84,75,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(85,75,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(86,75,6,76,5,'stores',0,2,0),(87,77,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(88,77,71,78,5,'-keyword-chandlery',0,2,0),(89,77,6,79,5,'chandlers',0,2,0),(90,80,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(91,80,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(92,80,6,81,5,'shipchandlers',0,2,0),(93,82,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(94,82,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(95,82,6,83,5,'ship_chander',0,2,0),(96,84,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(97,84,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(98,84,6,85,5,'ship_chandlers',0,2,0),(99,86,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(100,86,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(101,86,6,87,5,'chandlier',0,2,0),(102,88,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(103,88,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(104,88,6,89,5,'chandliers',0,2,0),(105,90,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(106,90,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(107,90,6,91,5,'shipchandlier',0,2,0),(108,92,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(109,92,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(110,92,6,93,5,'shipchandliers',0,2,0),(111,94,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(112,94,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(113,94,6,95,5,'chandlary',0,2,0),(114,96,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(115,96,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(116,96,6,97,5,'chand',0,2,0),(117,98,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(118,98,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(119,98,6,99,5,'shipschandlers',0,2,0),(120,100,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(121,100,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(122,100,6,101,5,'chandlering',0,2,0),(123,102,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(124,102,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(125,102,6,103,5,'shipchandlering',0,2,0),(126,104,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(127,104,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(128,104,6,105,5,'chandlrey',0,2,0),(129,106,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(130,106,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(131,106,6,107,5,'chandlrer',0,2,0),(132,108,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(133,108,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(134,108,6,109,5,'chandlrers',0,2,0),(135,110,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(136,110,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(137,110,6,111,5,'shipchandlrey',0,2,0),(138,112,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(139,112,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(140,112,6,113,5,'shipchandlrer',0,2,0),(141,114,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(142,114,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(143,114,6,115,5,'shipchandlrers',0,2,0),(144,116,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(145,116,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(146,116,6,117,5,'chandle',0,2,0),(147,118,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(148,118,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(149,118,6,119,5,'shipchandle',0,2,0),(150,120,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(151,120,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(152,120,6,68,5,'chandlery',0,2,0),(153,121,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(154,121,6,122,5,'ISSA',0,2,0),(155,121,18,123,5,'issa-xml',0,2,0),(156,77,71,66,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(157,124,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(158,124,6,125,5,'Hamburg',0,2,0),(159,124,18,126,5,'hamburg-xml',0,2,0),(160,127,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(161,127,6,128,5,'Rotterdam',0,2,0),(162,127,18,129,5,'rotterdam-xml',0,2,0),(163,130,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(164,130,6,131,5,'Shanghai',0,2,0),(165,130,18,132,5,'shanghai-xml',0,2,0),(166,133,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(167,133,6,134,5,'Singapore',0,2,0),(168,133,18,135,5,'singapore-xml',0,2,0),(169,136,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(170,136,9,124,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(171,136,6,137,5,'hamburg',0,2,0),(172,138,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(173,138,9,127,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(174,138,6,139,5,'rotterdam',0,2,0),(175,140,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(176,140,9,130,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(177,140,6,141,5,'shanghai',0,2,0),(178,142,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(179,142,9,133,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(180,142,6,143,5,'singapore',0,2,0),(181,144,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(182,144,71,136,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(183,144,6,137,5,'hamburg',0,2,0),(184,145,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(185,145,71,138,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(186,145,6,139,5,'rotterdam',0,2,0),(187,146,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(188,146,71,140,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(189,146,6,141,5,'shanghai',0,2,0),(190,147,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(191,147,71,142,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(192,147,6,143,5,'singapore',0,2,0),(193,148,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(194,148,9,121,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(195,148,6,149,5,'issa',0,2,0),(196,150,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(197,150,71,148,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(198,150,6,149,5,'issa',0,2,0),(199,151,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(200,151,71,136,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(201,151,6,152,5,'germany',0,2,0),(202,153,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(203,153,71,138,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(204,153,6,154,5,'netherlands',0,2,0),(205,155,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(206,155,71,140,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(207,155,6,156,5,'china',0,2,0),(208,157,3,9,5,'http-rdf-myshipserv-com-schema-zone',0,0,0),(209,157,6,158,5,'Copenhagen',0,2,0),(210,157,18,159,5,'copenhagen-xml',0,2,0),(211,160,3,67,5,'http-rdf-myshipserv-com-schema-keyw',0,0,0),(212,160,9,157,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(213,160,6,161,5,'copenhagen',0,2,0),(214,162,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(215,162,71,160,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(216,162,6,161,5,'copenhagen',0,2,0),(217,163,3,70,5,'http-rdf-myshipserv-com-schema-syno',0,0,0),(218,163,71,160,5,'http-rdf-myshipserv-com-ontology-rd',0,2,0),(219,163,6,164,5,'denmark',0,2,0);
/*!40000 ALTER TABLE `arc_tests_triple` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-04-19  9:05:58
