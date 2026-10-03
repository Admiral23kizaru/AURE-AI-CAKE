CREATE OR REPLACE VIEW recommended_cakes AS
SELECT c.*, COUNT(r.id) AS verified_review_count, ROUND(AVG(r.rating),2) AS average_rating
FROM cakes c
JOIN reviews r ON r.cake_id=c.id AND r.is_verified=1
JOIN order_headers h ON h.id=r.order_header_id
 AND h.user_id=r.user_id
 AND h.order_type='normal'
 AND h.status='Completed'
 AND h.payment_status='paid'
JOIN users u ON u.id=r.user_id AND u.phone_verified_at IS NOT NULL
GROUP BY c.id
HAVING COUNT(r.id)>=50 AND AVG(r.rating)>8.0;
