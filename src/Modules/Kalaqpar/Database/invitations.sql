-- Migration 4 extends the original rooms/invitation system, without rebuilding it.
-- The original UNIQUE(invitation) covers active AND permanently reserved codes.
CREATE UNIQUE INDEX kalaqpar_rooms_legacy_invitation ON kalaqpar_rooms(legacy_invitation);

CREATE TRIGGER kalaqpar_invitation_insert BEFORE INSERT ON kalaqpar_rooms
WHEN (NEW.mode='private' AND (NEW.invitation IS NULL OR typeof(NEW.invitation)<>'text' OR
      NEW.invitation NOT GLOB '[1-9][0-9][0-9][0-9][0-9][0-9]')) OR
     (NEW.mode='public' AND (NEW.invitation IS NOT NULL OR NEW.legacy_invitation IS NOT NULL))
BEGIN SELECT RAISE(ABORT,'invalid invitation code'); END;

CREATE TRIGGER kalaqpar_invitation_update BEFORE UPDATE OF invitation,mode,legacy_invitation ON kalaqpar_rooms
WHEN NEW.mode IS NOT OLD.mode OR NEW.invitation IS NOT OLD.invitation OR NEW.legacy_invitation IS NOT OLD.legacy_invitation
BEGIN SELECT RAISE(ABORT,'room invitation is immutable'); END;

-- Keep even closed private rooms as reservations. Reusing a code is unsupported.
CREATE TRIGGER kalaqpar_invitation_reservation BEFORE DELETE ON kalaqpar_rooms
WHEN OLD.mode='private'
BEGIN SELECT RAISE(ABORT,'retain room invitation reservation'); END;
