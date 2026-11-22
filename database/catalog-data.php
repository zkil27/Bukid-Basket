<?php
declare(strict_types=1);

/**
 * Single source of catalog seed data + dish/image metadata.
 * Prices here are integer centavos (₱180 -> 18000). All prices are fictional.
 */

const DISHES = [
    ['id' => 'sinigang', 'label' => 'Sinigang'],
    ['id' => 'adobo', 'label' => 'Adobo'],
    ['id' => 'tinola', 'label' => 'Tinola'],
    ['id' => 'pinakbet', 'label' => 'Pinakbet'],
    ['id' => 'nilaga', 'label' => 'Nilaga'],
    ['id' => 'ensalada', 'label' => 'Ensalada'],
];

const CATALOG_SEED = [
    [
        'name' => 'Vegetables & Greens',
        'slug' => 'vegetables',
        'products' => [
            [
                'name' => 'Pechay (Bok Choy)',
                'description' => 'Tender native pechay, cut the morning it ships. Good in nilaga and ginisa.',
                'image' => '/products/pechay.jpg',
                'dishes' => ['nilaga', 'tinola'],
                'variants' => [
                    ['name' => 'Bundle 250g', 'sku' => 'VEG-PECHAY-250', 'price_centavos' => 3500, 'opening' => 40],
                    ['name' => '1kg', 'sku' => 'VEG-PECHAY-1', 'price_centavos' => 12000, 'opening' => 15],
                ],
            ],
            [
                'name' => 'Kangkong (Water Spinach)',
                'description' => 'Thick-stemmed kangkong for sinigang or adobong kangkong.',
                'image' => '/products/kangkong.jpg',
                'dishes' => ['sinigang', 'adobo'],
                'variants' => [
                    ['name' => 'Bundle', 'sku' => 'VEG-KANGKONG-B', 'price_centavos' => 2500, 'opening' => 50],
                ],
            ],
            [
                'name' => 'Sitaw (String Beans)',
                'description' => 'Long string beans, snapped fresh. A sinigang and pinakbet staple.',
                'image' => '/products/sitaw.jpg',
                'dishes' => ['sinigang', 'pinakbet', 'adobo'],
                'variants' => [
                    ['name' => 'Bundle 250g', 'sku' => 'VEG-SITAW-250', 'price_centavos' => 3000, 'opening' => 36],
                    ['name' => '1kg', 'sku' => 'VEG-SITAW-1', 'price_centavos' => 11000, 'opening' => 12],
                ],
            ],
            [
                'name' => 'Talong (Eggplant)',
                'description' => 'Long purple eggplant for tortang talong or grilling.',
                'image' => '/products/talong.jpg',
                'dishes' => ['pinakbet', 'ensalada'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'VEG-TALONG-500', 'price_centavos' => 5000, 'opening' => 30],
                    ['name' => '1kg', 'sku' => 'VEG-TALONG-1', 'price_centavos' => 9500, 'opening' => 14],
                ],
            ],
            [
                'name' => 'Ampalaya (Bitter Gourd)',
                'description' => 'Firm, bright-green ampalaya. Slice thin and salt to tame the bite.',
                'image' => '/products/ampalaya.jpg',
                'dishes' => ['pinakbet', 'ensalada'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'VEG-AMPALAYA-500', 'price_centavos' => 6500, 'opening' => 22],
                ],
            ],
            [
                'name' => 'Kamatis (Tomato)',
                'description' => 'Ripe native tomatoes for sawsawan, ensalada and every ginisa.',
                'image' => '/products/kamatis.jpg',
                'dishes' => ['sinigang', 'ensalada'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'VEG-KAMATIS-500', 'price_centavos' => 4500, 'opening' => 34],
                    ['name' => '1kg', 'sku' => 'VEG-KAMATIS-1', 'price_centavos' => 8500, 'opening' => 18],
                ],
            ],
            [
                'name' => 'Sibuyas (Red Onion)',
                'description' => 'Local red onions, cured and dry-skinned. Keeps for weeks.',
                'image' => '/products/sibuyas.jpg',
                'dishes' => ['adobo', 'ensalada'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'VEG-SIBUYAS-500', 'price_centavos' => 8000, 'opening' => 28],
                    ['name' => '1kg', 'sku' => 'VEG-SIBUYAS-1', 'price_centavos' => 15000, 'opening' => 16],
                ],
            ],
            [
                'name' => 'Kalabasa (Squash)',
                'description' => 'Sweet orange squash, sold by the slice. For ginataan and pinakbet.',
                'image' => '/products/kalabasa.jpg',
                'dishes' => ['pinakbet', 'nilaga'],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'VEG-KALABASA-1', 'price_centavos' => 6000, 'opening' => 20],
                ],
            ],
            [
                'name' => 'Okra',
                'description' => 'Young, small okra pods that stay crisp. Blanch for ensalada.',
                'image' => '/products/okra.jpg',
                'dishes' => ['sinigang', 'pinakbet', 'ensalada'],
                'variants' => [
                    ['name' => '250g', 'sku' => 'VEG-OKRA-250', 'price_centavos' => 3000, 'opening' => 26],
                ],
            ],
        ],
    ],
    [
        'name' => 'Fruits',
        'slug' => 'fruits',
        'products' => [
            [
                'name' => 'Mangga (Carabao Mango)',
                'description' => 'Sweet carabao mangoes, picked half-ripe so they ripen at home.',
                'image' => '/products/mangga.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'FRT-MANGGA-1', 'price_centavos' => 18000, 'opening' => 30],
                ],
            ],
            [
                'name' => 'Saging Lakatan (Banana)',
                'description' => 'Lakatan bananas by the hand, yellow and ready this week.',
                'image' => '/products/saging.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'FRT-LAKATAN-1', 'price_centavos' => 9000, 'opening' => 32],
                ],
            ],
            [
                'name' => 'Calamansi',
                'description' => 'Small and sour, for juice, sawsawan and pancit.',
                'image' => '/products/calamansi.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => '250g', 'sku' => 'FRT-CALAMANSI-250', 'price_centavos' => 4000, 'opening' => 40],
                    ['name' => '1kg', 'sku' => 'FRT-CALAMANSI-1', 'price_centavos' => 14000, 'opening' => 12],
                ],
            ],
            [
                'name' => 'Papaya',
                'description' => 'Whole ripe papaya, about 1 to 1.5kg each. Green papaya on request for tinola.',
                'image' => '/products/papaya.jpg',
                'dishes' => ['tinola'],
                'variants' => [
                    ['name' => 'Whole piece', 'sku' => 'FRT-PAPAYA-PC', 'price_centavos' => 7500, 'opening' => 18],
                ],
            ],
            [
                'name' => 'Pinya (Pineapple)',
                'description' => 'Queen pineapple from Cavite, golden and fragrant.',
                'image' => '/products/pinya.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => 'Whole piece', 'sku' => 'FRT-PINYA-PC', 'price_centavos' => 9000, 'opening' => 16],
                ],
            ],
        ],
    ],
    [
        'name' => 'Meat & Poultry',
        'slug' => 'meat',
        'products' => [
            [
                'name' => 'Manok Tagalog (Native Chicken)',
                'description' => 'Free-range native chicken, dressed whole. Firmer meat, deeper broth.',
                'image' => '/products/native-chicken.jpg',
                'dishes' => ['tinola', 'adobo'],
                'variants' => [
                    ['name' => 'Whole 1kg', 'sku' => 'MEAT-NATIVE-W1', 'price_centavos' => 42000, 'opening' => 14],
                ],
            ],
            [
                'name' => 'Liempo (Pork Belly)',
                'description' => 'Skin-on pork belly, sliced for inihaw or left whole for lechon kawali.',
                'image' => '/products/liempo.jpg',
                'dishes' => ['adobo', 'sinigang'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'MEAT-LIEMPO-500', 'price_centavos' => 19000, 'opening' => 24],
                    ['name' => '1kg', 'sku' => 'MEAT-LIEMPO-1', 'price_centavos' => 37000, 'opening' => 12],
                ],
            ],
            [
                'name' => 'Kasim (Pork Shoulder)',
                'description' => 'Lean pork shoulder, sold in one piece or cubed on request for adobo and menudo.',
                'image' => '/products/kasim.jpg',
                'dishes' => ['adobo', 'nilaga'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'MEAT-KASIM-500', 'price_centavos' => 17000, 'opening' => 20],
                    ['name' => '1kg', 'sku' => 'MEAT-KASIM-1', 'price_centavos' => 33000, 'opening' => 10],
                ],
            ],
            [
                'name' => 'Tadyang (Pork Ribs)',
                'description' => 'Meaty pork spareribs chopped for sinigang and nilaga.',
                'image' => '/products/tadyang.jpg',
                'dishes' => ['sinigang', 'nilaga'],
                'variants' => [
                    ['name' => '500g', 'sku' => 'MEAT-TADYANG-500', 'price_centavos' => 17500, 'opening' => 22],
                    ['name' => '1kg', 'sku' => 'MEAT-TADYANG-1', 'price_centavos' => 34000, 'opening' => 10],
                ],
            ],
            [
                'name' => 'Hita ng Manok (Chicken Legs)',
                'description' => 'Bone-in chicken legs, the juiciest cut for adobo and inihaw.',
                'image' => '/products/chicken-thigh.jpg',
                'dishes' => ['adobo', 'tinola'],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'MEAT-THIGH-1', 'price_centavos' => 24000, 'opening' => 18],
                ],
            ],
        ],
    ],
    [
        'name' => 'Eggs',
        'slug' => 'eggs',
        'products' => [
            [
                'name' => 'Itlog (Large Eggs)',
                'description' => 'Large brown eggs from a layer farm in Silang, collected this week.',
                'image' => '/products/itlog.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => 'Tray of 12', 'sku' => 'EGG-LARGE-12', 'price_centavos' => 11000, 'opening' => 30],
                    ['name' => 'Tray of 30', 'sku' => 'EGG-LARGE-30', 'price_centavos' => 26500, 'opening' => 14],
                ],
            ],
            [
                'name' => 'Itlog na Pula (Salted Egg)',
                'description' => 'Red salted duck eggs, ready to eat with kamatis and sibuyas.',
                'image' => '/products/itlog-pula.jpg',
                'dishes' => ['ensalada'],
                'variants' => [
                    ['name' => 'Tray of 12', 'sku' => 'EGG-SALTED-12', 'price_centavos' => 18000, 'opening' => 16],
                ],
            ],
        ],
    ],
    [
        'name' => 'Rice & Grains',
        'slug' => 'rice',
        'products' => [
            [
                'name' => 'Dinorado (Premium Rice)',
                'description' => 'Fragrant, soft-cooking dinorado. The Sunday-lunch rice.',
                'image' => '/products/dinorado.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'RICE-DINORADO-1', 'price_centavos' => 6200, 'opening' => 60],
                    ['name' => '5kg', 'sku' => 'RICE-DINORADO-5', 'price_centavos' => 30000, 'opening' => 24],
                    ['name' => 'Sack 25kg', 'sku' => 'RICE-DINORADO-25', 'price_centavos' => 145000, 'opening' => 8],
                ],
            ],
            [
                'name' => 'Sinandomeng (Well-milled Rice)',
                'description' => 'The everyday household rice. Cooks fluffy, keeps well overnight.',
                'image' => '/products/sinandomeng.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'RICE-SINAN-1', 'price_centavos' => 5200, 'opening' => 80],
                    ['name' => '5kg', 'sku' => 'RICE-SINAN-5', 'price_centavos' => 25000, 'opening' => 30],
                    ['name' => 'Sack 25kg', 'sku' => 'RICE-SINAN-25', 'price_centavos' => 122500, 'opening' => 10],
                ],
            ],
            [
                'name' => 'Malagkit (Glutinous Rice)',
                'description' => 'Sticky rice for biko, suman and champorado.',
                'image' => '/products/malagkit.jpg',
                'dishes' => [],
                'variants' => [
                    ['name' => '1kg', 'sku' => 'RICE-MALAGKIT-1', 'price_centavos' => 8500, 'opening' => 30],
                ],
            ],
            [
                'name' => 'Mais (White Corn)',
                'description' => 'Sweet white corn on the cob, sold by the piece. Boil or grill.',
                'image' => '/products/mais.jpg',
                'dishes' => ['nilaga'],
                'variants' => [
                    ['name' => 'Piece', 'sku' => 'RICE-MAIS-PC', 'price_centavos' => 2500, 'opening' => 40],
                ],
            ],
        ],
    ],
];

function image_for_name(string $name): ?string
{
    foreach (CATALOG_SEED as $cat) {
        foreach ($cat['products'] as $p) {
            if ($p['name'] === $name) {
                return $p['image'];
            }
        }
    }
    return null;
}

/** @return list<string> */
function dishes_for_name(string $name): array
{
    foreach (CATALOG_SEED as $cat) {
        foreach ($cat['products'] as $p) {
            if ($p['name'] === $name) {
                return $p['dishes'];
            }
        }
    }
    return [];
}

/** @return list<string> */
function product_images(): array
{
    $imgs = [];
    foreach (CATALOG_SEED as $cat) {
        foreach ($cat['products'] as $p) {
            $imgs[$p['image']] = true;
        }
    }
    $list = array_keys($imgs);
    sort($list);
    return $list;
}
