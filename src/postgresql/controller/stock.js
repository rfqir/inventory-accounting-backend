import pool from '../db/connection.js';

export async function getStocks(sku) {
    // Validasi input
    if (!Array.isArray(sku) || sku.length === 0) {
        throw new Error("sku parameter is required and must be a non-empty array.");
    }

    try {
        // Buat parameter SQL dengan jumlah placeholder sesuai sku
        const placeholders = sku.map((_, i) => `$${i + 1}`).join(", ");

        const query = `
            SELECT ssi.part_id, SUM(ssi.quantity) AS total, ssl.name as sku
            FROM public.stock_stockitem ssi
            JOIN public.stock_stockitem ssi_inner
                ON ssi.part_id = ssi_inner.part_id
            JOIN public.stock_stocklocation ssl
                ON ssi_inner.location_id = ssl.id
            WHERE ssl.name IN (${placeholders})
            GROUP BY ssi.part_id,ssl.name
        `;

        const { rows } = await pool.query(query, sku); // Eksekusi query dengan parameter
        
        return rows; // Kembalikan hasil query
    } catch (error) {
        console.error("Error fetching stocks:", error);
        throw error;
    }
}

export async function getStock(sku) {
    // Validasi input
    if (!Array.isArray(sku) || sku.length === 0) {
        throw new Error("sku parameter is required and must be a non-empty array.");
    }

    try {
        // Buat parameter SQL dengan jumlah placeholder sesuai sku
        const placeholders = sku.map((_, i) => `$${i + 1}`).join(", ");

        const query = `
            SELECT part_id,quantity, name as sku, stock_stocklocation.id as id  FROM public.stock_stocklocation
            inner JOIN public.stock_stockitem ON stock_stockitem.location_id = stock_stocklocation.id
            where name IN (${placeholders})
        `;

        const { rows } = await pool.query(query, sku); // Eksekusi query dengan parameter
        
        return rows; // Kembalikan hasil query
    } catch (error) {
        console.error("Error fetching stocks:", error);
        throw error;
    }
}
export async function skuGudang(part_id) {
    try {
        const query1 = `
            SELECT part_id, quantity, name, description, batch
            FROM public.stock_stocklocation
            INNER JOIN public.stock_stockitem ON stock_stockitem.location_id = stock_stocklocation.id
            WHERE stock_stockitem.part_id = $1
              AND stock_stocklocation.description = 'GUDANG'
              AND batch IS NOT NULL AND batch != ''
            ORDER BY batch ASC
            LIMIT 1
        `;

        const { rows: rows1 } = await pool.query(query1, [part_id]);

        if (rows1.length === 0) {
            const query2 = `
                SELECT part_id, quantity, name, description, batch
                FROM public.stock_stocklocation
                INNER JOIN public.stock_stockitem ON stock_stockitem.location_id = stock_stocklocation.id
                WHERE stock_stockitem.part_id = $1
                  AND stock_stocklocation.description = 'GUDANG'
                  AND name LIKE '1%'
                LIMIT 1
            `;

            const { rows: rows2 } = await pool.query(query2, [part_id]);
            return rows2;
        } else {
            return rows1;
        }
    } catch (error) {
        console.error("Error fetching stocks:", error);
        throw error;
    }
}

