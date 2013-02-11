-- ********************************************************
-- *                                                      *
-- * IMPORTANT NOTE                                       *
-- *                                                      *
-- * Do not import this file manually but use the Contao  *
-- * install tool to create and maintain database tables! *
-- *                                                      *
-- ********************************************************
 
--
-- Table `tl_page`
--
CREATE TABLE `tl_page` (
  `synccto_hash` char(1) NOT NULL default ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `tl_article`
--
CREATE TABLE `tl_article` (
  `synccto_hash` char(1) NOT NULL default ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `tl_content`
--
CREATE TABLE `tl_content` (
  `synccto_hash` char(1) NOT NULL default ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `tl_synccto_diff`
--
CREATE TABLE `tl_synccto_diff` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `table` varchar(255) NOT NULL default '',
  `row_id` int(10) unsigned NOT NULL default '0',
  `hash` text NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `keys` (`table`,`row_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;