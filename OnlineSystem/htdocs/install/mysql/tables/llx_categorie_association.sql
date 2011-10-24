-- ============================================================================
-- Copyright (C) 2005 Brice Davoleau <e1davole@iu-vannes.fr>
-- Copyright (C) 2005 Matthieu Valleton <mv@seeschloss.org>		
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- $Id: llx_categorie_association.sql,v 1.3 2011/08/03 01:25:28 eldy Exp $
-- ============================================================================

create table llx_categorie_association
(
  fk_categorie_mere   integer NOT NULL,
  fk_categorie_fille  integer NOT NULL
)ENGINE=innodb;
