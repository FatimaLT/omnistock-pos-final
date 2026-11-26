USE omnistock_db;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO categorias (nombre)
SELECT category_seed.nombre
FROM (
    SELECT 'Bebidas' AS nombre
    UNION ALL SELECT 'Botanas'
    UNION ALL SELECT 'Galletas'
    UNION ALL SELECT 'Abarrotes'
    UNION ALL SELECT 'Dulces'
) AS category_seed
WHERE NOT EXISTS (
    SELECT 1
    FROM categorias AS existing_category
    WHERE existing_category.nombre = category_seed.nombre
);

INSERT INTO productos (nombre, descripcion, precio, stock, imagen_url, categoria_id)
SELECT
    product_seed.nombre,
    product_seed.descripcion,
    product_seed.precio,
    product_seed.stock,
    product_seed.imagen_url,
    category.id
FROM (
    SELECT 'Refresco de cola 600 ml' AS nombre, 'Botella individual, presentación de 600 ml.' AS descripcion, 18.00 AS precio, 24 AS stock, 'public/img/productos/refresco-cola-600ml.png' AS imagen_url, 'Bebidas' AS categoria
    UNION ALL SELECT 'Refresco de cola 2 L', 'Botella familiar de 2 litros.', 38.00, 12, 'public/img/productos/refresco-cola-2l.png', 'Bebidas'
    UNION ALL SELECT 'Refresco de naranja 600 ml', 'Bebida sabor naranja en botella individual.', 17.00, 18, 'public/img/productos/refresco-naranja-600ml.png', 'Bebidas'
    UNION ALL SELECT 'Agua purificada 1 L', 'Botella de agua purificada de un litro.', 14.00, 20, 'public/img/productos/agua-purificada-1l.png', 'Bebidas'
    UNION ALL SELECT 'Bebida de fruta 500 ml', 'Bebida sabor frutas en envase individual.', 15.00, 16, 'public/img/productos/bebida-fruta-500ml.png', 'Bebidas'
    UNION ALL SELECT 'Papas fritas clásicas 45 g', 'Papas fritas con sal, bolsa individual.', 19.00, 15, 'public/img/productos/papas-clasicas-45g.png', 'Botanas'
    UNION ALL SELECT 'Totopos de maíz familiar', 'Totopos de maíz salados para compartir.', 32.00, 9, 'public/img/productos/totopos-maiz.png', 'Botanas'
    UNION ALL SELECT 'Cacahuates salados 50 g', 'Cacahuates tostados con sal, bolsa de 50 g.', 16.00, 14, 'public/img/productos/cacahuates-salados-50g.png', 'Botanas'
    UNION ALL SELECT 'Palomitas para microondas 90 g', 'Palomitas con mantequilla para preparar en casa.', 22.00, 10, 'public/img/productos/palomitas-microondas-90g.png', 'Botanas'
    UNION ALL SELECT 'Galletas con chispas de chocolate 100 g', 'Galletas crujientes con chispas de chocolate.', 20.00, 13, 'public/img/productos/galletas-chocolate-100g.png', 'Galletas'
    UNION ALL SELECT 'Galletas tipo sándwich', 'Galletas de chocolate rellenas de crema.', 18.00, 17, 'public/img/productos/galletas-sandwich.png', 'Galletas'
    UNION ALL SELECT 'Galletas saladas 140 g', 'Galletas saladas para acompañar comidas.', 21.00, 11, 'public/img/productos/galletas-saladas-140g.png', 'Galletas'
    UNION ALL SELECT 'Leche entera 1 L', 'Leche entera ultrapasteurizada en envase de un litro.', 29.00, 8, 'public/img/productos/leche-entera-1l.png', 'Abarrotes'
    UNION ALL SELECT 'Atún en agua 140 g', 'Lata de atún en agua, contenido neto de 140 g.', 23.00, 18, 'public/img/productos/atun-agua-140g.png', 'Abarrotes'
    UNION ALL SELECT 'Arroz blanco 1 kg', 'Arroz blanco de grano largo en bolsa de un kilogramo.', 31.00, 10, 'public/img/productos/arroz-blanco-1kg.png', 'Abarrotes'
    UNION ALL SELECT 'Frijol negro 1 kg', 'Frijol negro seco, bolsa de un kilogramo.', 36.00, 8, 'public/img/productos/frijol-negro-1kg.png', 'Abarrotes'
    UNION ALL SELECT 'Sopa de pasta 200 g', 'Pasta corta para sopa, paquete de 200 g.', 12.00, 22, 'public/img/productos/sopa-pasta-200g.png', 'Abarrotes'
    UNION ALL SELECT 'Chocolate con leche, tablilla', 'Barra individual de chocolate con leche.', 17.00, 20, 'public/img/productos/chocolate-leche.png', 'Dulces'
    UNION ALL SELECT 'Caramelos surtidos 100 g', 'Bolsa de caramelos surtidos envueltos.', 24.00, 12, 'public/img/productos/caramelos-surtidos-100g.png', 'Dulces'
    UNION ALL SELECT 'Gomitas de fruta 80 g', 'Gomitas suaves sabor fruta, bolsa de 80 g.', 19.00, 14, 'public/img/productos/gomitas-fruta-80g.png', 'Dulces'
    UNION ALL SELECT 'Paleta de caramelo', 'Paleta individual sabor frutal.', 5.00, 30, 'public/img/productos/paleta-caramelo.png', 'Dulces'
) AS product_seed
INNER JOIN categorias AS category
    ON category.nombre = product_seed.categoria
WHERE NOT EXISTS (
    SELECT 1
    FROM productos AS existing_product
    WHERE existing_product.nombre = product_seed.nombre
);

COMMIT;
