--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local imageStatementRenderer = {}

-- Render a statement containing images.
--
-- @param table statement
-- @param string orientationImage
-- @param bool inlineQualifiers, default false
-- @return string wikitext
local render = function( self, statement, orientationImage, inlineQualifiers )
  local inlineQualifiers = inlineQualifiers or false
  local reference = ''
  local qualifier = ''
  local image = ''

  local referenceRenderer = self._entityrenderer:getReferenceRenderer()
  local qualifierRenderer = self._entityrenderer:getQualifierRenderer()

  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == 'mainsnak' then
        image = mw.wikibase.renderSnak( value )
      elseif key == 'references' then
        reference = referenceRenderer( value )
      elseif key == 'qualifiers' then
        qualifier = qualifierRenderer( value )
      end
    end
  end
  local result = '[[File:' .. image .. '|thumb|' .. orientationImage .. '|340x280px|'
  if inlineQualifiers == true then
    result = result .. reference .. ' ' .. qualifier .. ']]'
  else
    result = result .. reference .. ' ' .. ']]' .. qualifier
  end
  return result
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
imageStatementRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( imageStatementRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( statement, orientationImage, inlineQualifiers )
    return self:_render( statement, orientationImage, inlineQualifiers )
  end
end

return imageStatementRenderer
