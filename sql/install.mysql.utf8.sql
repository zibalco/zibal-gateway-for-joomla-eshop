DELETE FROM #__eshop_payments WHERE name = 'eshop_zibal';

INSERT INTO `#__eshop_payments`(`id`, `name`, `title`, `author`, `creation_date`, `copyright`, `license`, `author_url`, `version`, `description`, `params`, `ordering`, `published`) VALUES 
(20, 'eshop_zibal', 'zibal payment', 'Zibal', '2021-09-06 00:00:00', 'Copyright 2021 Zibal', 'http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2', 'https://zibal.ir', '1.0', 'This is Zibal payment for Eshop', NULL, 20, 0);
