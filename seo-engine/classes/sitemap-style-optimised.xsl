<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9">

  <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes" doctype-public="-//W3C//DTD HTML 4.01 Transitional//EN" doctype-system="http://www.w3.org/TR/html4/loose.dtd"/>

  <xsl:template match="/">
    <html>
      <head>
        <title>
          <xsl:choose>
            <xsl:when test="sm:sitemapindex">Sitemap Index</xsl:when>
            <xsl:when test="sm:urlset">Sitemap</xsl:when>
            <xsl:otherwise>Sitemap Information</xsl:otherwise>
          </xsl:choose>
        </title>
        <meta name="description" content="HTML Sitemap, providing an overview of website structure."/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <style>
          /* Base Styles - Minimal */
          * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
          }

          body {
            font-family: Arial, Helvetica, sans-serif; /* Standard system fonts */
            background-color: #ffffff; /* White background */
            color: #333333; /* Dark gray text */
            line-height: 1.5;
            padding: 15px;
          }

          .container {
            max-width: 960px;
            margin: 20px auto;
            padding: 0 10px;
          }

          h1 {
            font-size: 1.8em; /* Reduced size */
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
            color: #000000; /* Black */
          }

          p.description {
            font-size: 1em;
            text-align: center;
            margin-bottom: 30px;
            color: #555555; /* Medium gray */
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
          }

          table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border: 1px solid #cccccc; /* Simple border */
            margin-bottom: 30px;
            table-layout: fixed;
          }

          table thead tr {
            background-color: #f0f0f0; /* Light gray header */
            color: #000000;
            font-size: 0.95em;
            font-weight: bold;
            text-align: left;
          }

          table th,
          table td {
            padding: 10px 15px;
            text-align: left;
            font-size: 0.9em;
            border-bottom: 1px solid #dddddd; /* Lighter border for rows */
          }
          
          table th {
            font-weight: bold;
          }

          table td:first-child,
          table th:first-child {
            text-align: left; /* Numbering on the left */
            width: 8%;
          }
          
          table th.loc-col, table td.loc-col { width: 50%; }
          table th.lastmod-col, table td.lastmod-col { width: 20%; }
          table th.priority-col, table td.priority-col { width: 10%; text-align: center; }
          table th.changefreq-col, table td.changefreq-col { width: 12%; text-align: center;}

          table tbody tr:last-child td {
             border-bottom: none;
          }

          table td a {
            color: #0000EE; /* Standard link blue */
            text-decoration: none;
            font-weight: normal;
            word-break: break-all;
          }

          table td a:hover,
          table td a:focus {
            color: #00008B; /* Darker blue for hover */
            text-decoration: underline;
          }
          
          .no-data {
            color: #777777; 
            font-style: italic;
          }

          .footer {
            text-align: center;
            padding: 15px;
            font-size: 0.85em;
            color: #777777;
            border-top: 1px solid #cccccc;
            margin-top: 30px;
          }
          .footer a {
            color: #0000EE;
            text-decoration: none;
          }
          .footer a:hover {
            text-decoration: underline;
          }

          /* Responsive adjustments - kept for usability */
          @media screen and (max-width: 768px) {
            body { padding: 10px; }
            .container { margin: 15px auto; }
            h1 { font-size: 1.6em; }
            p.description { font-size: 0.95em; }

            table {
              display: block;
              overflow-x: auto;
              border: 1px solid #ccc; /* Ensure border is visible for block table */
            }
            
            table thead {
              display: none; 
            }

            table tbody tr {
              display: block;
              margin-bottom: 10px;
              border: 1px solid #dddddd;
              background-color: #fff !important; /* Override any alternating row colors */
            }
            
            table tbody tr:nth-child(even) { /* No alternating colors on mobile cards */
                background-color: #fff !important;
            }

            table td {
              display: block;
              text-align: right; 
              padding-left: 45%; /* Space for label */
              position: relative;
              border-bottom: 1px solid #eeeeee;
            }
            
            table td:last-child {
                border-bottom: none;
            }

            table td:before {
              content: attr(data-label);
              position: absolute;
              left: 10px;
              width: calc(45% - 20px); /* Adjust based on padding-left */
              padding-right: 10px;
              font-weight: bold;
              text-align: left; 
              white-space: nowrap;
            }
            
            table td.loc-col { word-break: break-all; }
            
            /* Reset text-align for these specific columns on mobile if needed, else they inherit from td */
            table td:first-child,
            table th:first-child, /* Though th is hidden, keep for consistency if shown */
            table th.priority-col, table td.priority-col,
            table th.changefreq-col, table td.changefreq-col {
                text-align: right; /* data label on left, value on right */
                width: auto;
            }
          }

          @media screen and (max-width: 480px) {
             h1 { font-size: 1.4em; }
             table td { padding-left: 35%; }
             table td:before { width: calc(35% - 15px); left: 8px; }
          }
        </style>
      </head>
      <body>
        <div class="container">
          <xsl:choose>
            <xsl:when test="sm:sitemapindex">
              <h1>Sitemap Index</h1>
              <p class="description">This is a sitemap index file, listing other sitemaps.</p>
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th class="loc-col">Sitemap Location</th>
                    <th class="lastmod-col">Last Modified</th>
                  </tr>
                </thead>
                <tbody>
                  <xsl:for-each select="sm:sitemapindex/sm:sitemap">
                    <tr>
                      <td data-label="#">
                        <xsl:value-of select="position()"/>
                      </td>
                      <td data-label="Sitemap Location" class="loc-col">
                        <a href="{sm:loc}" target="_blank" rel="noopener noreferrer">
                          <xsl:value-of select="sm:loc"/>
                        </a>
                      </td>
                      <td data-label="Last Modified" class="lastmod-col">
                        <xsl:choose>
                          <xsl:when test="sm:lastmod and sm:lastmod != ''">
                            <xsl:value-of select="substring-before(sm:lastmod, 'T')"/>
                            <xsl:text> </xsl:text>
                            <xsl:variable name="timePart" select="substring-after(sm:lastmod, 'T')" />
                            <xsl:choose>
                                <xsl:when test="contains($timePart, 'Z')">
                                    <xsl:value-of select="substring-before($timePart, 'Z')"/>
                                </xsl:when>
                                <xsl:when test="contains($timePart, '+')">
                                    <xsl:value-of select="substring-before($timePart, '+')"/>
                                </xsl:when>
                                <xsl:when test="contains($timePart, '-') and string-length(substring-after($timePart, '-')) = 5 and substring(substring-after($timePart, '-'),3,1) = ':'"> <xsl:value-of select="substring-before($timePart, '-')"/>
                                </xsl:when>
                                <xsl:otherwise> <xsl:value-of select="$timePart"/>
                                </xsl:otherwise>
                            </xsl:choose>
                          </xsl:when>
                          <xsl:otherwise>
                            <span class="no-data">—</span>
                          </xsl:otherwise>
                        </xsl:choose>
                      </td>
                    </tr>
                  </xsl:for-each>
                </tbody>
              </table>
            </xsl:when>

            <xsl:when test="sm:urlset">
              <h1>Sitemap</h1>
              <p class="description">This sitemap lists all the URLs for this section of the website.</p>
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th class="loc-col">URL</th>
                    <th class="priority-col">Priority</th>
                    <th class="changefreq-col">Change Freq.</th>
                    <th class="lastmod-col">Last Modified</th>
                  </tr>
                </thead>
                <tbody>
                  <xsl:for-each select="sm:urlset/sm:url">
                    <tr>
                      <td data-label="#">
                        <xsl:value-of select="position()"/>
                      </td>
                      <td data-label="URL" class="loc-col">
                        <a href="{sm:loc}" target="_blank" rel="noopener noreferrer">
                          <xsl:value-of select="sm:loc"/>
                        </a>
                      </td>
                      <td data-label="Priority" class="priority-col">
                        <xsl:choose>
                            <xsl:when test="sm:priority and sm:priority != ''">
                                <xsl:value-of select="sm:priority"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <span class="no-data">—</span>
                            </xsl:otherwise>
                        </xsl:choose>
                      </td>
                      <td data-label="Change Freq." class="changefreq-col">
                        <xsl:choose>
                            <xsl:when test="sm:changefreq and sm:changefreq != ''">
                                <xsl:value-of select="sm:changefreq"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <span class="no-data">—</span>
                            </xsl:otherwise>
                        </xsl:choose>
                      </td>
                      <td data-label="Last Modified" class="lastmod-col">
                        <xsl:choose>
                          <xsl:when test="sm:lastmod and sm:lastmod != ''">
                            <xsl:value-of select="substring-before(sm:lastmod, 'T')"/>
                            <xsl:text> </xsl:text>
                            <xsl:variable name="timePart" select="substring-after(sm:lastmod, 'T')" />
                            <xsl:choose>
                                <xsl:when test="contains($timePart, 'Z')">
                                    <xsl:value-of select="substring-before($timePart, 'Z')"/>
                                </xsl:when>
                                <xsl:when test="contains($timePart, '+')">
                                    <xsl:value-of select="substring-before($timePart, '+')"/>
                                </xsl:when>
                                <xsl:when test="contains($timePart, '-') and string-length(substring-after($timePart, '-')) = 5 and substring(substring-after($timePart, '-'),3,1) = ':'"> <xsl:value-of select="substring-before($timePart, '-')"/>
                                </xsl:when>
                                <xsl:otherwise> <xsl:value-of select="$timePart"/>
                                </xsl:otherwise>
                            </xsl:choose>
                          </xsl:when>
                          <xsl:otherwise>
                            <span class="no-data">—</span>
                          </xsl:otherwise>
                        </xsl:choose>
                      </td>
                    </tr>
                  </xsl:for-each>
                </tbody>
              </table>
            </xsl:when>

            <xsl:otherwise>
              <div style="text-align: center; padding: 40px;">
                <h1>Unknown Sitemap Format</h1>
                <p class="description">We couldn’t find a <code>&lt;urlset&gt;</code> or <code>&lt;sitemapindex&gt;</code> root element in the XML file.</p>
                <p><a href="javascript:history.back()" style="color: #0000EE; text-decoration:none; font-weight:bold;">Go Back</a></p>
              </div>
            </xsl:otherwise>
          </xsl:choose>

          <div class="footer">
            <p>Generated by <a href="https://meowapps.com/seo-engine/" target="_blank" rel="noopener noreferrer">SEO Engine</a> | HTML Sitemap</p>
          </div>
        </div>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>