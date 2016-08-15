--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local labelRenderer = {}

-- Get a human readable label for an entity id as wikitext.
--
-- @param string entityId
-- @return wikitext label or linked entityId if no label available
labelRenderer.render = function( entityId )
  local label = mw.wikibase.label( entityId )
  if label ~= nil then
    label = mw.text.nowiki( label )
  else
    label = '[' .. mw.wikibase.getEntityUrl( entityId ) .. ' ' .. entityId .. ']'
  end
  return label
end

return labelRenderer
