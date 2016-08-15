--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local descriptionRenderer = {}

-- Get a human readable description for an entity id as wikitext.
--
-- @param string entityId
-- @return string wikitext
descriptionRenderer.render = function( entityId )
  local description = mw.wikibase.description( entityId )

  if description ~= nil then
    return '<div class="articleplaceholer-description"><p>' .. mw.text.nowiki( description ) .. '</p></div>'
  else
    return ''
  end
end

return descriptionRenderer
