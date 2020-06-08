--[[
	@license GPL-2.0-or-later
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local referenceRenderer = {}

-- Remove the references that include a Snak with the property id to hide.
-- @param table references
-- @return table newRefs
local removeReferencesByProperty = function( propertyToHide, references )
  local newRefs = {}

  for key, reference in pairs(references) do
    local display = true
    for propRef, snakRef in pairs( reference['snaks'] ) do

      if propRef == propertyToHide then
        display = false
        break
      end
    end
    if display then
      table.insert( newRefs, reference )
    end
  end

  return newRefs
end

-- Render a reference.
--
-- @param table references
-- @return string wikitext
local render = function( self, references )
  local snaksRenderer = self._entityrenderer:getSnaksRenderer()
  local frame = self._entityrenderer.frame
  local referencesWikitext = {}

  if self._entityrenderer.referencePropertyToHide ~= nil then
    references = removeReferencesByProperty( self._entityrenderer.referencePropertyToHide, references )
  end

  local i, reference = next( references, nil )

  if i then
    self._entityrenderer.setHasReferences( true )

    while i do
      local referenceWikitext = snaksRenderer( reference['snaks'] )
      -- Prefix reference name with r as the hash might be numeric
      local referenceTagName = 'r' .. mw.hash.hashValue( 'crc32', referenceWikitext )
      table.insert(
        referencesWikitext,
        frame:extensionTag( 'ref', referenceWikitext, { name = referenceTagName } )
      )

      i, reference = next( references, i )
    end
  end

  return table.concat( referencesWikitext, "" )
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
referenceRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( referenceRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( references )
    return self:_render( references )
  end
end

return referenceRenderer
