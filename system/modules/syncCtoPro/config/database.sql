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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table` varchar(255) NOT NULL,
  `row_id` int(11) NOT NULL,
  `hash` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keys` (`table`,`row_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;