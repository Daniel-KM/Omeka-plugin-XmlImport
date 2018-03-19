XML Import (plugin for Omeka)
=============================

[XML Import] is a plugin for [Omeka] that allows to import data and files and to
update records from one or multiple XML files via a generic or a custom XSLT
sheet. It's usefull to import documents and records from other places or from an
older installation of Omeka.

Process uses the plugin [CSV Import+], an improved version [CSV Import], so all
imports can be managed in one place.

You should create xsl sheets that convert your original xml files into csv ones.
It's possible too to convert your xml files into an intermediate simple format
with only items, files and elements (Dublin Core or other formats), that will be
automatically imported. See examples.

Some default sheets are provided, in particular for Omeka export (v4 and v5),
and [Mets], a common format used for digital papers (only for profiles with
Dublin Core).

In most cases now, it's recommended to use [Archive Folder], a simpler tool that
doesn't use [CSV Import+], so there is no issue with delimiters, enclosures,
white spaces, and multilines contents. [Archive Folder] uses the same sheets
than the ones that uses the xml intermediate format. The pivot format is a very
simple one, as the Dublin Core.


Installation
------------

Install first the plugin [CSV Import+], with a version greater or equal to 2.3,
required since release 2.16. The official [CSV Import] can be used only with
older releases and with some old formats.

Then uncompress files and rename plugin folder "XmlImport".

Then install it like any other Omeka plugin and follow the config instructions.

* Directory of xsl sheets

The process uses xsl sheets to convert your xml files into csv ones. So the path
to the directory where are your sheets should be set. By default, this is the
path to the default sheets.

* XSLT processor

Xslt has two main versions:  xslt 1.0 and xslt 2.0. The first is often installed
with php via the extension "php-xsl" or the package "php5-xsl", depending on
your system. It is until ten times slower than xslt 2.0 and sheets are more
complex to write.

So it's recommended to install an xslt 2 processor, that can process xslt 1.0
and xslt 2.0 sheets. The command can be configured in the configuration page of
the plugin. Use "%1$s", "%2$s", "%3$s", without escape, for the file input, the
stylesheet, and the output.

Examples for Debian 6, 7, 8 / Ubuntu / Mint (with the package "libsaxonb-java"):
```
saxonb-xslt -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s
```

Examples for Debian 8 / Ubuntu / Mint (with the package "libsaxonhe-java"):
```
CLASSPATH=/usr/share/java/Saxon-HE.jar java net.sf.saxon.Transform -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s
```

Example for Fedora / RedHat / Centos / Mandriva / Mageia:
```
saxon -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s
```

To test your installation, you need to be able to process such a command line:

For Saxon-B on Debian 6...:
```
cd /path/to/Omeka/plugins/XmlImport
saxonb-xslt -ext:on -versionmsg:off -warnings:silent -s:'xml_files/test_generic_item_automap.xml' -xsl:'libraries/xsl/generic.xsl' -o:'/tmp/test.csv'
```

For Saxon-HE on Debian 8...:
```
cd /path/to/Omeka/plugins/XmlImport
CLASSPATH=/usr/share/java/Saxon-HE.jar java net.sf.saxon.Transform -ext:on -versionmsg:off -warnings:silent -s:'xml_files/test_generic_item_automap.xml' -xsl:'libraries/xsl/generic.xsl' -o:'/tmp/test.csv'
```

For Saxon on Fedora...:
```
cd /path/to/Omeka/plugins/XmlImport
saxon -ext:on -versionmsg:off -warnings:silent -s:'xml_files/test_generic_item_automap.xml' -xsl:'libraries/xsl/generic.xsl' -o:'/tmp/test.csv'
```

Note: Only saxon is currently supported as xslt 2 processor. Because Saxon is a
Java tool, a JRE should be installed, for example "openjdk-8-jre-headless".

Note: Warnings are processed as errors. That's why the parameter "-warnings:silent"
is important to be able to process an import with a bad xsl sheet. It can be
removed with default xsl, that doesn't warn anything.

Anyway, if there is no xslt2 processor installed, the command field should be
cleared. The plugin will use the default xslt 1 processor of php, if installed.


Examples
--------

Since [CSV Import+] release 2.2-full, only the "Manage" format is available.
See older releases to manage old formats incompatible with this one.

A lot of examples of xml files are available in the xml_files folder. They are
many because a new one is built for each new feature. The last ones uses all of
them. Furthermore, some files may be updated with a second file to get full
data. This is just to have some examples of update or records.

Some provided stylesheets need an xslt 2 processor, but there is an equivalent
sheet for xslt 1 processor. All xslt 1 sheets can be processed by an xslt 2
processor.

Because XML Import is currently only a converter into csv, you need to set the
options for csv in the form. Recommended values are delimiter: tabulation,
enclosure: empty, element, tag and file delimiters: pipe.

1. `test_generic_item.xml`

    A basic list of three books with images of Wikipedia, with non Dublin Core
    tags. To try it, choose options "One xml file", "Items", and the xsl sheet
    `generic_item.xsl` (or `generic_item.xslt1.xsl` if an external processor is
    not set).

    With release 2.15, choose the identifier field "Dublin Core : Title" and
    extra data "Perhaps", so a manual mapping will be done, where the special
    value "Identifier" will be set to the title.

2. `test_generic_item_automap.xml`

    The same list with some Dublin Core attributes in order to automap the xml
    tags with the Omeka fields. Parameters are the same than the previous file,
    but you may delete previous import before this one.

    With release 2.15, cf. #1, but the extra data can be "No".

3. `test_item.xml`

    This is another example of a flat format, with an unknown node. To use it,
    use the same parameters, plus a specific parameter in the last field:
    `node = my_record`.

    With release 2.15, cf. #1.

4. `test_omeka_xml_output_v5.xml`

    An export of two different items with files and files metadata. To try it,
    use one of the three other sheets: `omeka_xml_output_v4_report.xsl`,
    `omeka_xml_output_v4_item.xsl`, `omeka_xml_output_v5_mixed.xsl` or
    `omeka_xml_output_v5_manage.xsl` and check the respective format.

    They allow to import Omeka Xml output files (version 4.0 and 4.1, included
    in Omeka 1.5, and version 5.0, included in Omeka 2.0), that you can get
    when you export your records to this format (simply click on the link at
    the bottom of the admin/items page, or on the link in each item page). The
    last one is recommanded with light or heavy and simple or complex data.
    First sheets don't manage files metadata. Collections should be created
    before import (none in the xml test files). Note that Omeka 1.5 outputs only
    urls of fullsize files, so you may change them before import.

    With release 2.15, choose the identifier field "Table Identifier", extra
    data "No" and stylesheet `omeka_xml_output_v5.xsl`.

5. `test_mixed.xml`

6. `test_mixed_update.xml`

    A full example of all features of [CSV Import+] via Xml, and file to
    update the previous one.

    To try them, use format "Mixed" for the first and "Update" for the second,
    check the options "Create collections" and "Contains extra data" and the xsl
    sheet `advanced_mixed.xsl`. If [Geolocation] is installed, it will be used
    to set the location of items.

    These two files are not importable with release 2.15.

7. `test_manage_local.xml`

    This format load test files from the plugin. Use the sheet `advanced_manage.xsl`.
    The identifier field is "Dublin Core:Identifier". The parameters are:
    ```
    base_file = http://localhost/path/to/omeka
    ```
    If local paths are allowed in CSV Import, they may be:
    ```
    base_file = /path/to/omeka
    ```
    These parameters should be removed for other test files.

8. `test_manage_from_mixed.xml`

9. `test_manage_from_mixed_update.xml`

    These files contain the same data than the two previous ones, but they are
    adapted for the format "Manage". Formats "Mixed" and "Update" are deprecated
    in [CSV Import+], so this one is recommended. It's simpler and allows
    import, update and remove with the same file and the same sheet. To import
    them, remove previous data and use the sheet `advanced_manage.xsl`.
    Note that in these examples, the identifier field is "Dublin Core:Title"
    (the same examples in CsvImport use "Dublin Core:Identifier").

10. `test_mag.xml`

    "Metadati Amministrativi e Gestionali" ([Mag]) is an xml format similar to
    [Mets]. It is used in Italy to manage administrative data about digitalized
    documents.

    To try it, select the format "Manage", the identifier field "Dublin Core:Identifier",
    the sheet "mag2document.xsl", check the option "Intermediate stylesheet" and
    add these parameters:
    ```
    base_url = http://localhost/path/to/omeka
    document_path =
    ```
    If local paths are allowed in CSV Import, they may be:
    ```
    base_url = /path/to/omeka
    document_path =
    ```
    The parameter "document_path" depends on the structure of the folders where
    are saved files and the way they are set in xml files. For this test, it
    should be empty. Other parameters of the xsl sheet can be set similarly.
    These parameters should be removed for other test files.

If your xsl sheet builds a csv file with "Csv Report", "Mixed records" or
"Manage" format, they can be  imported directly without mapping. "Mixed" and
"Manage" formats can be used only with the [CSV Import+] fork. These formats
are useful to import multiple types of documents (text, image, video...) and
their metadata in one time.

These xsl sheets can be chained or adapted to any needs and xml formats.

Generic files with Dublin Core content can be imported with the name of the node
that represents a record.The first level node is automatically used. To use
another level, the stylesheet parameter "node = record_name".

Note about delimiters:
As XML Import uses CSV Import, delimiters are used. Recommended delimiters are
special characters allowed in xml 1.0: tabulation "\t" for column delimiter,
carriage return "\r"  for element, tag and file delimiters, with the Unix end
of line "\n" (line feed). They can be used with an empty enclosure.
If fields contain paragraphs, another element delimiter should be used,
preferably another Ascii character, like pipe "|".


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [plugin issues] page on GitHub.


License
-------

This plugin is published under [Apache licence v2].


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM])
* [Scholars' Lab]

First version of this plugin has been built by [Ethan Gruber] for [Scholars' Lab]
of University of Virginia Library (see [Scholars' Lab Omeka plugins] page).
This plugin has been updated for [École des Ponts ParisTech], [Pop Up Archive]
and [Mines ParisTech].


Copyright
---------

* Copyright Scholars' Lab, 2010 (GenericXmlImporter v1.0)
* Copyright Daniel Berthereau, 2012-2016


[XML Import]: https://github.com/Daniel-KM/Omeka-plugin-XmlImport
[Omeka]: https://omeka.org
[CSV Import+]: https://github.com/Daniel-KM/Omeka-plugin-CsvImportPlus
[CSV Import]: https://github.com/omeka/plugin-CsvImport
[Archive Folder]: https://github.com/Daniel-KM/Omeka-plugin-ArchiveFolder
[Geolocation]: https://omeka.org/add-ons/plugins/geolocation
[Mag]: http://www.iccu.sbn.it/opencms/opencms/it/main/standard/metadati/pagina_267.html
[Mets]: https://www.loc.gov/standards/mets
[plugin issues]: https://github.com/Daniel-KM/Omeka-plugin-XmlImport/issues
[Apache licence v2]: https://www.apache.org/licenses/LICENSE-2.0.html
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
[Ethan Gruber]: mailto:ewg4x@virginia.edu
[Scholars' Lab]: https://github.com/scholarslab
[Scholars' Lab Omeka plugins]: http://www.scholarslab.org/research/omeka-plugins/
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Pop Up Archive]: http://popuparchive.org/
[Mines ParisTech]: http://bib.mines-paristech.fr
