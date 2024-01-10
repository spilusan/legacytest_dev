This script applies buyer data to the DB from a CSV file.

Usage:

php companyLoader.php <environment> <csv file path>

environment = development | testing | production
csv file path = path to CSV file to be applied

The CSV fields are as follows (from column 0 going right):

0: (string) 'M' = apply modification to DB, or skip row for any other value
1: (int) Buyer organisation code
2: (string) 'Y' = Set buyer active; other values set buyer inactive.
3: (string) Company name.
4: (string) Company city (free text).
5: (string) Company country (2 letter country code as per country table)..
6: (string) 'Y' | 'N' = Company may / may not be join requested.
7: (string) 'Y' | 'N' = Company opts out / does not opt out of review functionality.
8: (string) '1' | '0' | 'T' = Company to be anonymised / not to be anonymised / to be anonymised except for TN suppliers.
9: (string) Anonymised company name (if anonymised).
10: (string) Anonymised company location (if anonymised).

Note: the first row of the CSV file is considered to be a header row, and is skipped.

There is an example CSV file in test/test.csv

Warning - be VERY careful when running this script, you could damage company data.
