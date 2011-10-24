-- ===================================================================
-- Copyright (C) 2001-2002,2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2004      Laurent Destailleur  <eldy@users.sourceforge.net>
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
-- $Id: llx_paiement.sql,v 1.5 2011/08/03 01:25:40 eldy Exp $
-- ===================================================================


-- Satut, 0 ou 1, 1 n'est plus supprimable
-- fk_export_compta 0 pas exporte

create table llx_paiement
(
  rowid            integer AUTO_INCREMENT PRIMARY KEY,
  datec            datetime,           -- date de creation
  tms              timestamp,
  datep            datetime,           -- payment date
  amount           double(24,8) DEFAULT 0,
  fk_paiement      integer NOT NULL,
  num_paiement     varchar(50),
  note             text,
  fk_bank          integer NOT NULL DEFAULT 0,
  fk_user_creat    integer,            -- utilisateur qui a cree l'info
  fk_user_modif    integer,            -- utilisateur qui a modifie l'info
  statut           smallint DEFAULT 0 NOT NULL,
  fk_export_compta integer DEFAULT 0 NOT NULL

)ENGINE=innodb;
