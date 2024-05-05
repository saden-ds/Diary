# ************************************************************
# Sequel Ace SQL dump
# Version 20062
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: 127.0.0.1 (MySQL 8.0.32)
# Database: Diary
# Generation Time: 2024-05-05 18:00:19 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table assignment
# ------------------------------------------------------------

DROP TABLE IF EXISTS `assignment`;

CREATE TABLE `assignment` (
  `assignment_id` int unsigned NOT NULL AUTO_INCREMENT,
  `assignment_type` varchar(128) DEFAULT NULL,
  `assignment_description` text,
  `assignment_end_datetime` datetime DEFAULT NULL,
  `schedule_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table conversation
# ------------------------------------------------------------

DROP TABLE IF EXISTS `conversation`;

CREATE TABLE `conversation` (
  `conversation_id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`conversation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table lesson
# ------------------------------------------------------------

DROP TABLE IF EXISTS `lesson`;

CREATE TABLE `lesson` (
  `lesson_id` int unsigned NOT NULL AUTO_INCREMENT,
  `lesson_name` varchar(255) DEFAULT NULL,
  `lesson_description` text,
  `user_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`lesson_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table lesson_invite
# ------------------------------------------------------------

DROP TABLE IF EXISTS `lesson_invite`;

CREATE TABLE `lesson_invite` (
  `lesson_invite_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lesson_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`lesson_invite_id`),
  UNIQUE KEY `lesson_user` (`lesson_id`,`user_email`),
  KEY `lesson_id` (`lesson_id`),
  KEY `user_email` (`user_email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table lesson_time
# ------------------------------------------------------------

DROP TABLE IF EXISTS `lesson_time`;

CREATE TABLE `lesson_time` (
  `lesson_time_id` int unsigned NOT NULL AUTO_INCREMENT,
  `lesson_time_number` int DEFAULT NULL,
  `lesson_time_start_at` char(5) NOT NULL,
  `lesson_time_end_at` char(5) NOT NULL,
  PRIMARY KEY (`lesson_time_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table lesson_user
# ------------------------------------------------------------

DROP TABLE IF EXISTS `lesson_user`;

CREATE TABLE `lesson_user` (
  `lesson_user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `lesson_user_created_at` datetime DEFAULT NULL,
  `lesson_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`lesson_user_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table message
# ------------------------------------------------------------

DROP TABLE IF EXISTS `message`;

CREATE TABLE `message` (
  `message_id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `message_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `message_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table schedule
# ------------------------------------------------------------

DROP TABLE IF EXISTS `schedule`;

CREATE TABLE `schedule` (
  `schedule_id` int unsigned NOT NULL AUTO_INCREMENT,
  `schedule_date` date DEFAULT NULL,
  `schedule_name` varchar(255) DEFAULT NULL,
  `lesson_id` int unsigned DEFAULT NULL,
  `lesson_time_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`schedule_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `lesson_time_id` (`lesson_time_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



# Dump of table user
# ------------------------------------------------------------

DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_active` tinyint(1) NOT NULL DEFAULT '0',
  `user_confirmed_at` datetime DEFAULT NULL,
  `user_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_firstname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_lastname` varchar(255) DEFAULT NULL,
  `user_encrypted_password` char(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `user_salt` char(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_email` (`user_email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
