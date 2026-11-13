# Content refers to Sylius entities by code and to CMS records by UUID

Fields and rich text refer to Sylius entities (products, taxons, channels, customer groups and so on) by their code. They refer to CMS records, such as entries and media, by UUID. They never use an auto-increment ID or a foreign key.

The Sylius admin doesn't let you change a code once it's set, and the same code exists in every environment. Content can therefore move from staging to production without remapping IDs.

The cost is that the database can't enforce these references. A deleted product leaves a dangling code, so rendering and the admin have to handle references that no longer resolve.
