<?php
/*
 * Copyright 2012 - 2014 Bokxing IT, http://www.bokxing-it.nl
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
 * Project page: <http://github.com/1afa/sabre-zarafa/>
 *
 */

namespace SabreZarafa;

class Restrict
{
	public static function
	rOr ($first, $second)
	{
		return [ RES_OR, [ $first, $second ] ];
	}

	public static function
	rAnd ($first, $second)
	{
		return [ RES_AND, [ $first, $second ] ];
	}

	public static function
	rNot ($first)
	{
		return [ RES_NOT, [ $first ] ];
	}

	public static function
	exist ($property)
	{
		return [ RES_EXIST, [ ULPROPTAG => $property ] ];
	}

	public static function
	propval ($property, $value, $relop)
	{
		// $relop can be RELOP_EQ, RELOP_NE, etc
		return self::rAnd
			( self::exist($property)
			, [ RES_PROPERTY
			  , [ RELOP     => $relop
			    , ULPROPTAG => $property
			    , VALUE     => [ $property => $value ]
			    ]
			  ]
			) ;
	}

	public static function
	propstring ($property, $value)
	{
		// Useful to restrict results to a string, like 'IPF.Contact'.
		return self::rAnd
			( self::exist($property)
			, [ RES_CONTENT
			  , [ FUZZYLEVEL => FL_PREFIX | FL_IGNORECASE
			    , ULPROPTAG  => $property
			    , VALUE      => [ $property => $value ]
			    ]
			  ]
			) ;
	}

	public static function
	hidden ()
	{
		return self::rAnd
			( self::exist(PR_ATTR_HIDDEN)
			, self::propval(PR_ATTR_HIDDEN, true, RELOP_EQ)
			) ;
	}

	public static function
	nonhidden ()
	{
		return self::rOr
			( self::rNot(self::exist(PR_ATTR_HIDDEN))
			, self::propval(PR_ATTR_HIDDEN, false, RELOP_EQ)
			) ;
	}

	public static function
	tbl_restrict_propval ($table, $property, $value, $relop)
	{
		return mapi_table_restrict($table, self::propval($property, $value, $relop));
	}

	public static function
	tbl_restrict_none ($table)
	{
		return mapi_table_restrict($table, []);
	}
}
