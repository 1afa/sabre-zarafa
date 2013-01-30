<?php
/*
 * Copyright 2012 - 2013 Bokxing IT, http://www.bokxing-it.nl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * "Zarafa" is a registered trademark of Zarafa B.V.
 *
 * This software use SabreDAV, an open source software distributed
 * with New BSD License. Please see <http://code.google.com/p/sabredav/>
 * for more information about SabreDAV
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Project page: <http://github.com/bokxing-it/sabre-zarafa/>
 *
 */

function
restrict_or ($first, $second)
{
	return array(RES_OR, array($first, $second));
}

function
restrict_and ($first, $second)
{
	return array(RES_AND, array($first, $second));
}

function
restrict_not ($first)
{
	return array(RES_NOT, array($first));
}

function
restrict_exist ($property)
{
	return array(RES_EXIST, array(ULPROPTAG => $property));
}

function
restrict_propval ($property, $value, $relop)
{
	// $relop can be RELOP_EQ, RELOP_NE, etc
	return restrict_and(
		restrict_exist($property),
		array	( RES_PROPERTY
			, array	( RELOP     => $relop
				, ULPROPTAG => $property
				, VALUE     => array($property => $value)
				)
			)
		);
}

function
restrict_propstring ($property, $value)
{
	// Useful to restrict results to a string, like 'IPF.Contact'.
	return restrict_and(
		restrict_exist($property),
		array	( RES_CONTENT
			, array	( FUZZYLEVEL => FL_PREFIX | FL_IGNORECASE
				, ULPROPTAG  => $property
				, VALUE      => array($property => $value)
				)
			)
		);
}

function
restrict_hidden ()
{
	return restrict_and(
		restrict_exist(PR_ATTR_HIDDEN),
		restrict_propval(PR_ATTR_HIDDEN, TRUE, RELOP_EQ)
	);
}

function
restrict_nonhidden ()
{
	return restrict_or(
		restrict_not(restrict_exist(PR_ATTR_HIDDEN)),
		restrict_propval(PR_ATTR_HIDDEN, FALSE, RELOP_EQ)
	);
}

function
tbl_restrict_propval ($table, $property, $value, $relop)
{
	return mapi_table_restrict($table, restrict_propval($property, $value, $relop));
}

function
tbl_restrict_none ($table)
{
	return mapi_table_restrict($table, array());
}
