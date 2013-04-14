<?php

class bdApi_FileSums
{
	public static function getHashes()
	{
		return array (
  'library/bdApi/ControllerAdmin/AuthCode.php' => 'defb34b7f3b9fed82d341238c25c96c7',
  'library/bdApi/ControllerAdmin/Client.php' => 'ae680030ed19a807d3170f73a2ab68e2',
  'library/bdApi/ControllerAdmin/RefreshToken.php' => '08ce0d77da86adddcd7d547af8bedd8f',
  'library/bdApi/ControllerAdmin/Token.php' => '4fee93f238a3bfca9ea375d183762751',
  'library/bdApi/ControllerApi/Abstract.php' => '316ec75f5ea96b1bccf40274692469af',
  'library/bdApi/ControllerApi/Asset.php' => '05002f22f8158d4de40adc45ebdca442',
  'library/bdApi/ControllerApi/Category.php' => 'daabc8687dd7c3257d7d5f1da8543804',
  'library/bdApi/ControllerApi/Error.php' => 'f3bf76d3c860390acd9b4dc5b23d2218',
  'library/bdApi/ControllerApi/Forum.php' => '7b888c5e53e0af5a154e1f4680a5bc5c',
  'library/bdApi/ControllerApi/Index.php' => '089989f3a1816f2197d0c18d3443effb',
  'library/bdApi/ControllerApi/Node.php' => 'dce07ff85e7e3f0195ea3a80f0d2a790',
  'library/bdApi/ControllerApi/OAuth.php' => 'cb84c3814f496402c4cb87e2582da558',
  'library/bdApi/ControllerApi/Post.php' => 'aab1d8e143d88280e44a271a33d9988b',
  'library/bdApi/ControllerApi/Thread.php' => '0a283617e4fcc31e22ee763250b89470',
  'library/bdApi/ControllerApi/User.php' => 'e1c6d5dc178960584bb4aa36a5eaa193',
  'library/bdApi/CronEntry/CleanUp.php' => '2bdb4b4f0c1218eb5e39dc6221b5ce8c',
  'library/bdApi/Data/Helper/Core.php' => '0c25ea4a7731b9e079c3ae500be89807',
  'library/bdApi/DataWriter/AuthCode.php' => '52aa68ad700b37ecc1fa20b81c01b62e',
  'library/bdApi/DataWriter/Client.php' => '7e81d530db1b4dd01e2bb474d15214d1',
  'library/bdApi/DataWriter/RefreshToken.php' => '710fe939a3bdfb84f4bfc080a7a4cb0b',
  'library/bdApi/DataWriter/Token.php' => 'f92610daf3a17646ea592031a448925b',
  'library/bdApi/Dependencies.php' => 'f51550151e6ed4c9db38ff84dc58ef64',
  'library/bdApi/Installer.php' => 'a3aefaa0bdcd9e28bee6cfa1fbd5befc',
  'library/bdApi/Link.php' => 'ba8ae27808542b58849e1a0bb7f50011',
  'library/bdApi/Listener.php' => '1ed23c9bd509f4ba18bc7e2326ba1142',
  'library/bdApi/Model/AuthCode.php' => '25b6e0db62025eda69d24ed501805b2e',
  'library/bdApi/Model/Client.php' => '311f3be617cd17e8a0e6813c452fbba6',
  'library/bdApi/Model/OAuth2.php' => 'c87e6a163a32a21b63b56ba0fd809260',
  'library/bdApi/Model/RefreshToken.php' => '7a430f94b86039910f7209b48006e0b7',
  'library/bdApi/Model/Token.php' => '6815eea2d66471831026766c26a1d33f',
  'library/bdApi/OAuth2.php' => '3737ffe1d30c58449c476adbf7aeed69',
  'library/bdApi/Option.php' => 'c258d48492b9cb1931fff33ad6a65635',
  'library/bdApi/Route/PrefixAdmin/AuthCode.php' => '6ae4cf1aafaf43f7ae7cd5d9d0a0df9b',
  'library/bdApi/Route/PrefixAdmin/Client.php' => '0ec3878d436cabf75f699c1b0adc6633',
  'library/bdApi/Route/PrefixAdmin/RefreshToken.php' => '785792de37c97cca302374430d5c5063',
  'library/bdApi/Route/PrefixAdmin/Token.php' => '3c72b187ee2214d004c93ae1b3bb5943',
  'library/bdApi/Route/PrefixApi/Abstract.php' => '1182fa6d6ae9c59b8c8763ac5386d263',
  'library/bdApi/Route/PrefixApi/Assets.php' => '3df9baa8e5c917b2437259a379a9fe4b',
  'library/bdApi/Route/PrefixApi/Categories.php' => '18594fbdb72be17aecdcef980a2dd459',
  'library/bdApi/Route/PrefixApi/Forums.php' => 'cd4800df1048e1fdb50a76da640d1ab5',
  'library/bdApi/Route/PrefixApi/Index.php' => '9418441be460acc37ef2ffb407ae23a9',
  'library/bdApi/Route/PrefixApi/OAuth.php' => '39d1a2a2f40c8dd154893d382fa5a6d7',
  'library/bdApi/Route/PrefixApi/Posts.php' => 'd6f00027a71e6e4788c6d55312e29fdd',
  'library/bdApi/Route/PrefixApi/Threads.php' => '977a33c0f82ba496878d2ae3df3d3812',
  'library/bdApi/Route/PrefixApi/Users.php' => 'bcabdb45ab1c8fc4c8f9951c21dd35cb',
  'library/bdApi/Route/PrefixApi.php' => '70565086ca83215dcc55a269072f1790',
  'library/bdApi/Session.php' => '2fad76170c5068312d274635c3e64b9b',
  'library/bdApi/Template/Helper/Core.php' => '8d0ca801b21a0248a9026e303d7792df',
  'library/bdApi/ViewAdmin/Token/Add.php' => 'a62b0c35514ffec0687a141d610cd09b',
  'library/bdApi/ViewApi/Base.php' => 'd34b8eac08c2261ec328dfbdb99f0154',
  'library/bdApi/ViewPublic/Account/Api/Data.php' => '86589c3880cc5d666e016bc5dc6eb696',
  'library/bdApi/ViewPublic/Account/Api/Index.php' => 'f444684f7065ddb4684db5b269a6a4e5',
  'library/bdApi/ViewPublic/Account/Authorize.php' => '600db6b57fb202c473636660e44018fe',
  'library/bdApi/ViewRenderer/Json.php' => '4e17df7246f9f0b6df3009790f26f604',
  'library/bdApi/ViewRenderer/Xml.php' => '8236228096b9d033c06e31a486cc228b',
  'library/bdApi/XenForo/ControllerPublic/Account.php' => '47ad4fff46cfbd608179110c1668c3cf',
  'library/bdApi/XenForo/ControllerPublic/Register.php' => '642c121cac314fc5aa4eeb4366d1c7b8',
  'library/bdApi/XenForo/DataWriter/DiscussionMessage/Post.php' => '75946b6ceee8ace4643a971f184e8384',
  'library/bdApi/XenForo/Model/Category.php' => 'd38303c66fce081d3344d12deaf7e5f7',
  'library/bdApi/XenForo/Model/Forum.php' => 'ded8098a26eb3da8f9ca6b24bc8fea2a',
  'library/bdApi/XenForo/Model/Post.php' => '2599dc71ad1e7ece1a30f9e6dd922618',
  'library/bdApi/XenForo/Model/Thread.php' => 'cdcd965a1568377ea609593e67bf2366',
  'library/bdApi/XenForo/Model/User.php' => '74873742e08976a881d6accc2025a678',
  'js/bdApi/full/sdk.js' => '639b9db11918dc956e0ffe2f09572f0d',
);
	}
}