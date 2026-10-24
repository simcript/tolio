# Playable content catalogue

The bundled catalogue contains **140 distinct Persian subjects: 49 flying and 91 non-flying**. The previous release contained 111 subjects (39 flying, 72 non-flying). The reduced target was chosen to retain familiar subjects and the existing proportions rather than fill a 700-item catalogue with obscure species or name variants.

## Composition and review

These are the source catalogue's subject groups, not additional database fields. Counts use largest-remainder rounding of the original proportions to 140. Every category differs from its original share by less than 0.4 percentage points.

| Group | Original | Added | Current |
| --- | ---: | ---: | ---: |
| Flying birds | 30 | 8 | 38 |
| Flying mammals | 1 | 0 | 1 |
| Flying insects | 8 | 2 | 10 |
| Non-flying animals | 40 | 11 | 51 |
| Objects, food and plants | 32 | 8 | 40 |
| Total | 111 | 29 | 140 |
| Flying | 39 | 10 | 49 |
| Non-flying | 72 | 19 | 91 |

The flying share changes from 35.14% to 35.00%. Names, classifications, catalogue order and artwork for the original 111 entries remain unchanged. New subjects are appended, so fresh installations retain the original ID order; the seed updates existing rows by their exact name and preserves their IDs.

The 29 additions were manually reviewed as distinct subjects, rather than synonyms, spelling variants, plurals or cosmetic modifiers:

| Group | Additions |
| --- | --- |
| Flying birds | قناری، مینا، یاکریم، کرکس، حواصیل، باکلان، دم‌جنبانک، کبک |
| Flying insects | ملخ، خرمگس |
| Non-flying animals | الاغ، اسب آبی، گورکن، سمور آبی، راکون، کفتار، گوریل، آفتاب‌پرست، مارمولک، عقرب، میگو |
| Objects, food and plants | انگور، خیار، بادمجان، گل رز، مسواک، شانه، چکش، عینک |

Classification follows ordinary flight ability. For example, the newly added chukar can fly despite spending much of its time on the ground; grasshoppers and horseflies belong in the flying group. The existing penguin and ostrich remain non-flying; the bat and existing flying insects remain flying. No vehicles, fictional subjects, gliding mammals or sex-specific winged insect forms were added.

There is no stored difficulty field, difficulty tier or difficulty weighting in the schema or selector. Difficulty review is therefore qualitative: retain the existing simple Persian naming style and mix of familiar household subjects, animals and recognizably different birds and insects. A numeric difficulty distribution cannot be inferred from the existing metadata.

Classification references used during manual review include Cornell Lab's Chukar and Eurasian Collared-Dove guides, Animal Diversity Web's *Alectoris chukar* account, the University of Arkansas Arthropod Museum's grasshopper account, and University of Maryland Extension's Horse and Deer Flies guide. This biological review is separate from automated count and integrity checks.

## Data and artwork

`Database/Content.php` is the authoritative catalogue. Each entry provides `name`, private `can_fly`, `asset` and SVG source. `Files/items/<asset>.svg` is the served illustration; the asset identifier is the first 24 characters of its SVG's SHA-256 hash. New artwork follows the existing original vector style and contains no answer labels, remote images or placeholder references. The original illustrations remain byte-for-byte unchanged.

`Database/Seeder.php` writes `kalaqpar_items` transactionally. `Services/Engine.php` selects only rows with `enabled=1`, then applies the existing category selection and recent-item exclusion rules. The catalogue adds no new selection behavior or metadata requirements.

The seed detects equivalent names after mapping Arabic `ك`, `ي`, `ى` to Persian `ک`, `ی` and removing whitespace, joining controls, combining marks and tatweel. This comparison does not rewrite display names. A duplicate or conflicting existing spelling aborts the entire seed so an operator can review it without silently merging subjects or changing identifiers.

## Fresh installations and upgrades

Use the normal maintenance procedure in `OPERATIONS.md`: back up storage, block web traffic, enable maintenance and stop a persistent worker if one is running. Then use the native commands:

```sh
php pano kalaqpar kalaqpar:migrate
php pano kalaqpar kalaqpar:seed
```

No content-specific schema migration is needed. The seed is safe to rerun and does not reset users, matches, responses, score events or results. It preserves existing `enabled` values and custom rows. Consequently, an installation with manually disabled or additional items can intentionally have a different playable count; do not re-enable or delete its content to force a count.

For a standard installation, the following must report 140 playable rows, 49 flying and 91 non-flying after seeding:

```sql
SELECT COUNT(*) AS playable, SUM(can_fly) AS flying,
       SUM(can_fly=0) AS non_flying
FROM kalaqpar_items WHERE enabled=1;
```

Verify normalized name uniqueness, IDs, required fields and the existence and hash of every referenced SVG as separate checks. Compare the original rows and assets with the pre-upgrade snapshot. Automated checks establish data consistency and eligibility; they do not establish biological correctness, familiarity or visual recognizability.
