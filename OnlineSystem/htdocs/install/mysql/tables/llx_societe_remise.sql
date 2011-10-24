-- ========================================================================
-- Copyright (C) 2000-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2011      Regis Houssin        <regis@dolibarr.fr>
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
-- $Id: llx_societe_remise.sql,v 1.5 2011/08/03 01:25:27 eldy Exp $
--
-- Historique evolution de la remise relative des tiers
-- ========================================================================

create table llx_societe_remise
(
  rowid           integer AUTO_INCREMENT PRIMARY KEY,
  fk_soc          integer NOT NULL,
  tms             timestamp,
  datec	          datetime,                            -- creation date
  fk_user_author  integer,                             -- creation user
  remise_client   double(6,3)  DEFAULT 0 NOT NULL,     -- discount
  note            text

)ENGINE=innodb;

