DROP TABLE IF EXISTS `table_name`;
CREATE TABLE IF NOT EXISTS `table_name` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `field1` varchar(32) NOT NULL,
  `field2` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2;

INSERT INTO `table_name` (`id`, `field1`, `field2`) VALUES(1, '6dbd01b4309de2c22b027eb35a3ce18b', '67e2fad0920514c2bcc287a1ea798cb1');
