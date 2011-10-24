-- ============================================================================
-- Copyright (C) 2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2005 Laurent Destailleur  <eldy@users.sourceforge.net>
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
-- $Id: llx_prelevement_facture.key.sql,v 1.2 2011/08/03 01:25:29 eldy Exp $
-- ============================================================================


ALTER TABLE llx_prelevement_facture ADD INDEX idx_prelevement_facture_fk_prelevement_lignes (fk_prelevement_lignes);


ALTER TABLE llx_prelevement_facture ADD CONSTRAINT fk_prelevement_facture_fk_prelevement_lignes FOREIGN KEY (fk_prelevement_lignes) REFERENCES llx_prelevement_lignes (rowid);

