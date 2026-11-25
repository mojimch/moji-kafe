USE moji_kafe;

SET @has_booking_token_hash = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'bookings'
      AND column_name = 'access_token_hash'
);
SET @booking_token_sql = IF(
    @has_booking_token_hash = 0,
    'ALTER TABLE bookings ADD COLUMN access_token_hash CHAR(64) NULL AFTER booking_code',
    'SELECT 1'
);
PREPARE add_booking_token_hash FROM @booking_token_sql;
EXECUTE add_booking_token_hash;
DEALLOCATE PREPARE add_booking_token_hash;

SET @has_order_token_hash = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND column_name = 'access_token_hash'
);
SET @order_token_sql = IF(
    @has_order_token_hash = 0,
    'ALTER TABLE orders ADD COLUMN access_token_hash CHAR(64) NULL AFTER order_code',
    'SELECT 1'
);
PREPARE add_order_token_hash FROM @order_token_sql;
EXECUTE add_order_token_hash;
DEALLOCATE PREPARE add_order_token_hash;

-- Existing rows keep NULL and remain code-only accessible for at most seven days
-- after created_at. New rows must store a hash of a random access token.
