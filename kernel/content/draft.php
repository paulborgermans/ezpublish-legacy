<?php
//
// Definition of List class
//
// Created on: <29-���-2002 16:14:57 sp>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ publish
// SOFTWARE RELEASE: 3.9.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2006 eZ systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*! \file list.php
*/
include_once( 'kernel/common/template.php' );
include_once( 'kernel/classes/ezcontentobjectversion.php' );
include_once( "lib/ezdb/classes/ezdb.php" );

$Module =& $Params['Module'];
$http =& eZHTTPTool::instance();

$Offset = $Params['Offset'];
$viewParameters = array( 'offset' => $Offset );

$user =& eZUser::currentUser();
if ( !$user->isLoggedIn() )
    return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );

$userID = $user->id();

if ( $http->hasPostVariable( 'RemoveButton' )  )
{
    if ( $http->hasPostVariable( 'DeleteIDArray' ) )
    {
        $deleteIDArray = $http->postVariable( 'DeleteIDArray' );
        $db =& eZDB::instance();
        $db->begin();
        foreach ( $deleteIDArray as $deleteID )
        {
            eZDebug::writeNotice( $deleteID, "deleteID" );
            $version = eZContentObjectVersion::fetch( $deleteID );
            $version->remove();
        }
        $db->commit();
    }
}

if ( $http->hasPostVariable( 'EmptyButton' )  )
{
    $versions = eZContentObjectVersion::fetchForUser( $userID );
    $db =& eZDB::instance();
    $db->begin();
    foreach ( array_keys( $versions ) as $key )
    {
        $version =& $versions[$key];
        $version->remove();
    }
    $db->commit();
}

$tpl =& templateInit();

$tpl->setVariable('view_parameters', $viewParameters );

$Result = array();
$Result['content'] =& $tpl->fetch( 'design:content/draft.tpl' );
$Result['path'] = array( array( 'text' => ezi18n( 'kernel/content', 'My drafts' ),
                                'url' => false ) );

?>
